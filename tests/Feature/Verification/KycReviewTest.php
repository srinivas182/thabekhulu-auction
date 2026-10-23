<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Models\AuditEntry;
use App\Domains\Verification\Actions\ApplyCheckResult;
use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Enums\Outcome;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Notifications\KycUpdate;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(AccessSeeder::class);
    $this->officer = User::factory()->staff(SystemRole::KycOfficer)->create();
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube']);
});

/** A submitted verification built through the buyer's real journey. */
function submittedVerification(User $buyer): Verification
{
    $img = fn (string $n) => UploadedFile::fake()->image($n, 600, 400);
    test()->actingAs($buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009087', 'front' => $img('f.jpg')]);
    test()->actingAs($buyer)->post(route('account.verification.selfie'), ['selfie' => $img('s.jpg')]);
    test()->actingAs($buyer)->post(route('account.verification.address'), ['marital_status' => 'single', 'proof_of_address' => $img('a.jpg')]);
    test()->actingAs($buyer)->post(route('account.verification.submit'));

    return Verification::where('user_id', $buyer->id)->firstOrFail();
}

function screenClear(User $officer, Verification $v, string $result = 'clear', ?string $note = null): void
{
    test()->actingAs($officer)->post(route('admin.kyc.screen', $v), ['result' => $result, 'lists' => 'FIC TFS, UN, PEP', 'note' => $note]);
}

it('lists submissions waiting for review', function () {
    submittedVerification($this->buyer);

    $this->actingAs($this->officer)->get(route('admin.kyc.index'))
        ->assertInertia(fn (Assert $p) => $p->component('admin/kyc/index')->has('verifications.data', 1)->where('counts.review', 1)->where('counts.screening', 0));
});

it('requires a clear screening in the last 30 days before verifying', function () {
    $v = submittedVerification($this->buyer);

    $this->actingAs($this->officer)->post(route('admin.kyc.approve', $v))->assertSessionHasErrors('decision');

    screenClear($this->officer, $v, 'possible_match');
    expect(Screening::count())->toBe(0); // a match needs details
    screenClear($this->officer, $v, 'possible_match', 'Name similar to a listed person; date of birth differs.');
    $this->actingAs($this->officer)->post(route('admin.kyc.approve', $v))->assertSessionHasErrors('decision');

    screenClear($this->officer, $v);
    Screening::query()->update(['screened_at' => now()->subDays(31)]);
    $this->actingAs($this->officer)->post(route('admin.kyc.approve', $v))->assertSessionHasErrors('decision');

    screenClear($this->officer, $v);
    $this->actingAs($this->officer)->post(route('admin.kyc.approve', $v))->assertSessionHasNoErrors();

    $v->refresh();
    expect($v->status)->toBe('verified')->and($v->reviewed_by)->toBe($this->officer->id)->and($v->expires_at)->not->toBeNull();
    Notification::assertSentTo($this->buyer, KycUpdate::class, fn ($n) => $n->kind === 'verified');
});

it('rejects with a reason, or reopens the submission to ask for more', function () {
    $v = submittedVerification($this->buyer);

    $this->actingAs($this->officer)->post(route('admin.kyc.request-info', $v), ['message' => 'Please upload a clearer selfie.'])->assertSessionHasNoErrors();
    expect($v->fresh()->status)->toBe('in_progress')->and($v->fresh()->info_requested)->toBe('Please upload a clearer selfie.');
    Notification::assertSentTo($this->buyer, KycUpdate::class, fn ($n) => $n->kind === 'info');

    $this->actingAs($this->buyer)->post(route('account.verification.selfie'), ['selfie' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post(route('account.verification.submit'));
    $this->actingAs($this->officer)->post(route('admin.kyc.reject', $v), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->officer)->post(route('admin.kyc.reject', $v), ['reason' => 'The ID photo does not match the selfie.'])->assertSessionHasNoErrors();

    expect($v->fresh()->status)->toBe('rejected');
    Notification::assertSentTo($this->buyer, KycUpdate::class, fn ($n) => $n->kind === 'rejected' && $n->reason === 'The ID photo does not match the selfie.');
});

it('a staff decision is final even when a late provider result arrives', function () {
    $v = submittedVerification($this->buyer);
    $this->actingAs($this->officer)->post(route('admin.kyc.reject', $v), ['reason' => 'Fraudulent document']);

    app(ApplyCheckResult::class)->handle($v->fresh(), 'smile_id', new CheckResult(Outcome::Passed), 'identity');

    expect($v->fresh()->status)->toBe('rejected');
});

it('decrypts documents only for KYC staff and logs every opening', function () {
    $v = submittedVerification($this->buyer);
    $doc = $v->documents()->where('kind', 'selfie')->firstOrFail();

    $this->actingAs($this->officer)->get(route('admin.kyc.document', [$v, $doc]))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
    expect(AuditEntry::where('event', 'document_viewed')->count())->toBe(1);

    $support = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($support)->get(route('admin.kyc.document', [$v, $doc]))->assertForbidden();
    $this->actingAs($this->buyer)->get(route('admin.kyc.document', [$v, $doc]))->assertForbidden();
});

it('separates approving from rejecting by permission', function () {
    $v = submittedVerification($this->buyer);
    $viewer = User::factory()->staff(SystemRole::Auctioneer)->create();
    $viewer->givePermissionTo('kyc.view');

    $this->actingAs($viewer)->get(route('admin.kyc.show', $v))->assertOk();
    $this->actingAs($viewer)->post(route('admin.kyc.approve', $v))->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.kyc.reject', $v), ['reason' => 'x'])->assertForbidden();
});

it('stores the refund account encrypted, checks it, and refuses changes while locked', function () {
    $v = submittedVerification($this->buyer);
    screenClear($this->officer, $v);
    $this->actingAs($this->officer)->post(route('admin.kyc.approve', $v));

    $this->actingAs($this->buyer)->post(route('account.bank.store'), [
        'bank' => 'Capitec', 'branch_code' => '470010', 'account_number' => '1234 5678 90', 'account_type' => 'savings',
        'holder_name' => 'Naledi Dube', 'proof' => UploadedFile::fake()->create('letter.pdf', 30, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $bank = BankAccount::firstOrFail();
    expect($bank->account_number)->toBe('1234567890')->and($bank->account_last4)->toBe('7890')->and($bank->status)->toBe('pending')
        ->and((string) DB::table('bank_accounts')->value('account_number'))->not->toContain('1234567890');

    $this->actingAs($this->officer)->post(route('admin.kyc.bank', $bank), ['decision' => 'verify'])->assertSessionHasNoErrors();
    expect($bank->fresh()->status)->toBe('verified');
    Notification::assertSentTo($this->buyer, KycUpdate::class, fn ($n) => $n->kind === 'bank_verified');

    $bank->forceFill(['locked' => true])->save(); // as Sprint 5 will do while a refund is pending
    $this->actingAs($this->buyer)->post(route('account.bank.store'), [
        'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => '62000000000', 'account_type' => 'cheque',
        'holder_name' => 'Naledi Dube', 'proof' => UploadedFile::fake()->create('letter.pdf', 30, 'application/pdf'),
    ])->assertSessionHasErrors('account_number');
    expect($bank->fresh()->bank)->toBe('Capitec');
});

it('sends a changed account back for checking', function () {
    BankAccount::create(['user_id' => $this->buyer->id, 'bank' => 'Capitec', 'branch_code' => '470010', 'account_number' => '1234567890', 'account_last4' => '7890',
        'account_type' => 'savings', 'holder_name' => 'Naledi Dube', 'status' => 'verified', 'verified_at' => now()]);

    $this->actingAs($this->buyer)->post(route('account.bank.store'), [
        'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => '62000000000', 'account_type' => 'cheque',
        'holder_name' => 'Naledi Dube', 'proof' => UploadedFile::fake()->create('letter.pdf', 30, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    expect(BankAccount::count())->toBe(1)->and(BankAccount::first()->status)->toBe('pending')->and(BankAccount::first()->verified_at)->toBeNull();
});

it('lists verified buyers who are due for re-screening', function () {
    $v = submittedVerification($this->buyer);
    screenClear($this->officer, $v);
    $this->actingAs($this->officer)->post(route('admin.kyc.approve', $v));
    Screening::query()->update(['screened_at' => now()->subDays(31)]);

    $this->actingAs($this->officer)->get(route('admin.kyc.index', ['tab' => 'screening']))
        ->assertInertia(fn (Assert $p) => $p->where('counts.screening', 1)->has('screeningDue', 1));
});
