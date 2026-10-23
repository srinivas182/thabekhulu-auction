<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Verification\Enums\CheckType;
use App\Domains\Verification\Http\Controllers\BuyerVerificationController;
use App\Domains\Verification\Models\ProviderSetting;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Support\ProviderRegistry;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(AccessSeeder::class);
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube']);
});

function doc(string $name = 'id.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 500);
}

function completeSteps(User $buyer, string $id = '8001015009087', string $marital = 'single'): void
{
    test()->actingAs($buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => $id, 'front' => doc(), 'back' => doc('back.jpg')])->assertSessionHasNoErrors();
    test()->actingAs($buyer)->post(route('account.verification.selfie'), ['selfie' => doc('me.jpg')])->assertSessionHasNoErrors();
    test()->actingAs($buyer)->post(route('account.verification.address'), ['marital_status' => $marital, 'proof_of_address' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
}

it('validates the SA ID number and stores it encrypted with its date of birth', function () {
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009086', 'front' => doc()])
        ->assertSessionHasErrors('id_number');

    $this->actingAs($this->buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '800101 5009 087', 'front' => doc()])
        ->assertSessionHasNoErrors();

    $v = Verification::firstOrFail();
    expect($v->id_number)->toBe('8001015009087')->and($v->date_of_birth->toDateString())->toBe('1980-01-01')
        ->and((string) DB::table('verifications')->value('id_number'))->not->toContain('8001015009087');
});

it('allows one account per ID number', function () {
    completeSteps($this->buyer);
    $other = User::factory()->create(['type' => 'buyer']);

    $this->actingAs($other)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009087', 'front' => doc()])
        ->assertSessionHasErrors('id_number');
});

it('stores documents encrypted on the private disk', function () {
    $file = doc();
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009087', 'front' => $file]);

    $stored = Verification::firstOrFail()->documents()->where('kind', 'id_front')->firstOrFail();
    $raw = Storage::disk('local')->get($stored->path);

    expect($raw)->not->toBe(file_get_contents($file->getRealPath()))
        ->and(BuyerVerificationController::read($stored))->toBe(file_get_contents($file->getRealPath()));
});

it('requires spouse details and signed consent when married in community of property', function () {
    $this->actingAs($this->buyer)->post(route('account.verification.address'), ['marital_status' => 'married_icop', 'proof_of_address' => doc('bill.jpg')])
        ->assertSessionHasErrors(['spouse_name', 'spouse_id_number', 'spouse_consent']);

    $this->actingAs($this->buyer)->post(route('account.verification.address'), [
        'marital_status' => 'married_icop', 'proof_of_address' => doc('bill.jpg'), 'spouse_name' => 'Thabo Dube',
        'spouse_id_number' => '8001015009087', 'spouse_consent' => UploadedFile::fake()->create('consent.pdf', 40, 'application/pdf'),
    ])->assertSessionHasNoErrors()->assertRedirect(); // a server error would also have "no errors"; require the redirect

    expect(Verification::firstOrFail()->spouse['name'])->toBe('Thabo Dube');
});

it('will not submit until every step is complete', function () {
    $this->actingAs($this->buyer)->post(route('account.verification.identity'), ['document_type' => 'sa_id', 'id_number' => '8001015009087', 'front' => doc()]);

    $this->actingAs($this->buyer)->post(route('account.verification.submit'))->assertSessionHasErrors('verification');
});

it('runs the SA ID check through the routed provider and sends documents to review', function () {
    ProviderSetting::create(['key' => 'smile_id', 'enabled' => true, 'mode' => 'sandbox', 'credentials' => ['partner_id' => '085', 'api_key' => 'k']]);
    app(ProviderRegistry::class)->route(CheckType::SaIdentity, 'smile_id');
    Http::fake(['testapi.smileidentity.com/v1/id_verification' => Http::response(['SmileJobID' => 'J1', 'Actions' => ['Verify_ID_Number' => 'Verified', 'Names' => 'Exact Match']])]);

    completeSteps($this->buyer);
    $this->actingAs($this->buyer)->post(route('account.verification.submit'))->assertRedirect(route('account.verification'));

    $v = Verification::firstOrFail();
    expect($v->checks['identity']['outcome'])->toBe('passed')->and($v->checks['identity']['provider'])->toBe('smile_id')
        ->and($v->checks['documents']['outcome'])->toBe('review')
        ->and($v->status)->toBe('review');
    Http::assertSent(fn ($r) => $r['id_number'] === '8001015009087' && $r['first_name'] === 'Naledi');
});

it('sends everything to manual review when no provider is routed, and locks the submission', function () {
    completeSteps($this->buyer);
    $this->actingAs($this->buyer)->post(route('account.verification.submit'));

    expect(Verification::firstOrFail()->status)->toBe('review');
    $this->actingAs($this->buyer)->post(route('account.verification.selfie'), ['selfie' => doc()])->assertSessionHasErrors('verification');
    $this->actingAs($this->buyer)->get(route('account.verification'))->assertInertia(fn (Assert $p) => $p
        ->component('account/verification')->where('verification.status', 'review')->where('verification.idNumberLast4', '9087')
        ->missing('verification.idNumber'));
});

it('is for buyers only', function () {
    $staff = User::factory()->staff(SystemRole::Support)->create();

    $this->actingAs($staff)->post(route('account.verification.selfie'), ['selfie' => doc()])->assertForbidden();
});
