<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BankMessage;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Notifications\KycUpdate;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->seed(AccessSeeder::class);
    $this->officer = User::factory()->staff(SystemRole::KycOfficer)->create();
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube']);
});

function kycSubmit(User $buyer, array $identity = []): Verification
{
    $img = fn (string $n) => UploadedFile::fake()->image($n, 600, 400);
    test()->actingAs($buyer)->post(route('account.verification.identity'), array_merge(['document_type' => 'sa_id', 'id_number' => '8001015009087', 'front' => $img('f.jpg')], $identity))->assertSessionHasNoErrors();
    test()->actingAs($buyer)->post(route('account.verification.selfie'), ['selfie' => $img('s.jpg')]);
    test()->actingAs($buyer)->post(route('account.verification.address'), ['marital_status' => 'single', 'proof_of_address' => $img('a.jpg')]);
    test()->actingAs($buyer)->post(route('account.verification.submit'))->assertSessionHasNoErrors();

    return Verification::where('user_id', $buyer->id)->latest('id')->firstOrFail();
}

function kycApprove(User $officer, Verification $v): void
{
    test()->actingAs($officer)->post(route('admin.kyc.screen', $v), ['result' => 'clear', 'lists' => 'FIC TFS, UN, PEP']);
    test()->actingAs($officer)->post(route('admin.kyc.approve', $v))->assertSessionHasNoErrors();
}

it('runs the request, reply, re-upload and resubmit as one conversation, keeping the old selfie', function () {
    $v = kycSubmit($this->buyer);
    $oldSelfie = $v->documents()->where('kind', 'selfie')->firstOrFail();

    $this->actingAs($this->officer)->post(route('admin.kyc.request-info', $v), ['message' => 'Please upload a clearer selfie.'])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post(route('account.verification.reply'), ['reply' => 'Done, new selfie in better light.'])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post(route('account.verification.selfie'), ['selfie' => UploadedFile::fake()->image('s2.jpg', 600, 400)])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post(route('account.verification.submit'))->assertSessionHasNoErrors();

    $v->refresh();
    expect($v->status)->toBeIn(['submitted', 'review'])
        ->and($v->documents()->where('kind', 'selfie')->count())->toBe(1)
        ->and($v->documents()->where('kind', 'selfie')->value('id'))->not->toBe($oldSelfie->id)
        ->and($oldSelfie->fresh()->superseded_at)->not->toBeNull()                      // kept, not deleted
        ->and(Storage::disk('local')->exists($oldSelfie->path))->toBeTrue()
        ->and($v->messages()->pluck('kind')->all())->toBe(['submitted', 'info_request', 'reply', 'document', 'submitted']);

    $this->actingAs($this->officer)->get(route('admin.kyc.show', $v))->assertInertia(fn ($p) => $p
        ->where('verification.messages.2.body', 'Done, new selfie in better light.')
        ->where('verification.messages.3.body', 'New selfie')
        ->where('verification.previousDocuments.0.kind', 'selfie')
        ->has('verification.documents', 3));
});

it('accepts a reply only while a request is open', function () {
    kycSubmit($this->buyer);
    $this->actingAs($this->buyer)->post(route('account.verification.reply'), ['reply' => 'Hello'])->assertStatus(422);
});

it('verifies for 24 months, or until the passport expires if sooner', function () {
    $v = kycSubmit($this->buyer);
    kycApprove($this->officer, $v);
    expect($v->fresh()->expires_at->toDateString())->toBe(now()->addMonths(24)->toDateString());

    $other = User::factory()->create(['type' => 'buyer']);
    $p = kycSubmit($other, ['document_type' => 'passport', 'id_number' => 'FN1234567', 'passport_country' => 'ZW',
        'date_of_birth' => now()->subYears(30)->toDateString(), 'passport_expires_on' => now()->addMonths(6)->toDateString()]);
    kycApprove($this->officer, $p);
    expect($p->fresh()->expires_at->toDateString())->toBe(now()->addMonths(6)->toDateString());
});

it('lets a buyer renew once verification has expired, keeping the old record', function () {
    $v = kycSubmit($this->buyer);
    kycApprove($this->officer, $v);
    $v->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($this->buyer)->get(route('account.verification'))->assertInertia(fn ($p) => $p->where('verification.expired', true));
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009087',
        'front' => UploadedFile::fake()->image('new.jpg', 600, 400)])->assertSessionHasNoErrors();

    expect(Verification::where('user_id', $this->buyer->id)->count())->toBe(2)
        ->and($v->fresh()->status)->toBe('verified')
        ->and(Verification::where('user_id', $this->buyer->id)->latest('id')->value('status'))->toBe('in_progress');
});

function bankDetails(string $file = 'letter.pdf'): array
{
    return ['bank' => array_key_first(BankAccount::BANKS), 'branch_code' => '250655', 'account_number' => '62000000001', 'account_type' => 'cheque',
        'holder_name' => 'Naledi Dube', 'proof' => UploadedFile::fake()->create($file, 60, 'application/pdf')];
}

it('lets the reviewer ask about a bank account, and the buyer reply and resubmit', function () {
    $v = kycSubmit($this->buyer);
    kycApprove($this->officer, $v);
    $this->actingAs($this->buyer)->post(route('account.bank.store'), bankDetails())->assertSessionHasNoErrors();
    $first = BankAccount::where('user_id', $this->buyer->id)->firstOrFail();

    $this->actingAs($this->officer)->post(route('admin.kyc.bank', $first), ['decision' => 'info'])->assertSessionHasErrors('reason');
    $this->actingAs($this->officer)->post(route('admin.kyc.bank', $first), ['decision' => 'info', 'reason' => 'The letter is older than three months.'])->assertSessionHasNoErrors();
    expect($first->fresh()->status)->toBe('info_requested');                                 // neither verified nor rejected
    Notification::assertSentTo($this->buyer, KycUpdate::class, fn ($n) => $n->kind === 'bank_info');

    $this->actingAs($this->buyer)->get(route('account.bank'))->assertInertia(fn ($p) => $p->where('account.infoRequested', true)->where('messages.1.body', 'The letter is older than three months.'));
    $this->actingAs($this->buyer)->post(route('account.bank.reply'), ['reply' => 'New letter attached.'])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->post(route('account.bank.store'), bankDetails('new-letter.pdf'))->assertSessionHasNoErrors();

    $second = BankAccount::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();
    expect($second->id)->not->toBe($first->id)->and($second->status)->toBe('pending');
    $this->actingAs($this->officer)->get(route('admin.kyc.show', $v))->assertInertia(fn ($p) => $p
        ->where('previousBanks.0.id', $first->id)->where('previousBanks.0.status', 'superseded')->has('bankMessages', 4));

    $this->actingAs($this->officer)->post(route('admin.kyc.bank', $second), ['decision' => 'verify'])->assertSessionHasNoErrors();
    expect(BankMessage::where('user_id', $this->buyer->id)->orderBy('id')->pluck('kind')->all())
        ->toBe(['submitted', 'info_request', 'reply', 'submitted', 'verified'])
        ->and(BankMessage::where('user_id', $this->buyer->id)->where('kind', 'submitted')->latest('id')->value('body'))->toBe('Submitted updated bank details and letter');
});

it('accepts a bank reply only while a request is open, and lets only reviewers ask', function () {
    $v = kycSubmit($this->buyer);
    kycApprove($this->officer, $v);
    $this->actingAs($this->buyer)->post(route('account.bank.store'), bankDetails())->assertSessionHasNoErrors();
    $bank = BankAccount::where('user_id', $this->buyer->id)->firstOrFail();

    $this->actingAs($this->buyer)->post(route('account.bank.reply'), ['reply' => 'Hello'])->assertStatus(422);
    $this->actingAs(User::factory()->staff(SystemRole::Finance)->create())->post(route('admin.kyc.bank', $bank), ['decision' => 'info', 'reason' => 'x'])->assertForbidden();
});
