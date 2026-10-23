<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Verification\Data\IdentityRequest;
use App\Domains\Verification\Enums\CheckType;
use App\Domains\Verification\Enums\Outcome;
use App\Domains\Verification\Models\ProviderSetting;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Providers\SmileIdProvider;
use App\Domains\Verification\Providers\SumsubProvider;
use App\Domains\Verification\Support\ProviderRegistry;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Provider hosts are faked per test; pages still render through the local SSR service.
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function enableSmile(): void
{
    test()->actingAs(test()->admin)->put(route('admin.verification.providers.update', 'smile_id'), [
        'enabled' => true, 'mode' => 'sandbox', 'credentials' => ['partner_id' => '085', 'api_key' => 'smile-secret-key-1234'],
    ])->assertSessionHasNoErrors();
}

it('signs Smile ID requests as documented and maps results', function () {
    $signed = SmileIdProvider::sign('085', 'key', '2026-10-24T08:00:00.000Z');
    expect($signed['signature'])->toBe(base64_encode(hash_hmac('sha256', '2026-10-24T08:00:00.000Z085sid_request', 'key', true)));

    Http::fake([
        'testapi.smileidentity.com/v1/id_verification' => Http::sequence()
            ->push(['SmileJobID' => 'J1', 'Actions' => ['Verify_ID_Number' => 'Verified', 'Names' => 'Exact Match']])
            ->push(['SmileJobID' => 'J2', 'Actions' => ['Verify_ID_Number' => 'Verified', 'Names' => 'Partial Match']])
            ->push(['SmileJobID' => 'J3', 'Actions' => ['Verify_ID_Number' => 'Not Verified'], 'ResultText' => 'ID Number Not Found'])
            ->push([], 500),
    ]);
    $smile = app(SmileIdProvider::class);
    $req = new IdentityRequest('01TEST', 'Naledi', 'Dube', '8001015009087', '1980-01-01');
    $creds = ['partner_id' => '085', 'api_key' => 'key'];

    expect($smile->checkIdentity($req, $creds, 'sandbox')->outcome)->toBe(Outcome::Passed)
        ->and($smile->checkIdentity($req, $creds, 'sandbox')->outcome)->toBe(Outcome::Review)
        ->and($smile->checkIdentity($req, $creds, 'sandbox')->outcome)->toBe(Outcome::Failed)
        ->and($smile->checkIdentity($req, $creds, 'sandbox')->outcome)->toBe(Outcome::Review);

    Http::assertSent(fn (HttpRequest $r) => $r['partner_id'] === '085' && $r['id_number'] === '8001015009087' && $r['country'] === 'ZA'
        && $r['partner_params']['job_id'] === '01TEST' && hash_equals(SmileIdProvider::sign('085', 'key', $r['timestamp'])['signature'], $r['signature']));
});

it('signs Sumsub requests as documented and creates the applicant', function () {
    $h = SumsubProvider::headers('tok', 'sec', 'post', '/resources/applicants?levelName=x', '{"a":1}', 1700000000);
    expect($h['X-App-Access-Sig'])->toBe(hash_hmac('sha256', '1700000000POST/resources/applicants?levelName=x{"a":1}', 'sec'))
        ->and($h['X-App-Access-Ts'])->toBe('1700000000');

    Http::fake(['api.sumsub.com/resources/applicants*' => Http::response(['id' => 'APP123'])]);
    $result = app(SumsubProvider::class)->checkIdentity(new IdentityRequest('01TEST', 'Ana', 'Silva', 'P1234567', null, 'PT', 'passport'),
        ['app_token' => 'tok', 'secret_key' => 'sec', 'level_name' => 'investor-kyc'], 'sandbox');

    expect($result->outcome)->toBe(Outcome::Review)->and($result->reference)->toBe('APP123');
    Http::assertSent(function (HttpRequest $r) {
        $path = '/resources/applicants?levelName=investor-kyc';

        return str_ends_with($r->url(), $path) && $r->hasHeader('X-App-Token', 'tok')
            && $r->header('X-App-Access-Sig')[0] === hash_hmac('sha256', $r->header('X-App-Access-Ts')[0].'POST'.$path.$r->body(), 'sec')
            && $r['externalUserId'] === '01TEST';
    });
});

it('reports connection tests from the provider response', function () {
    Http::fake(['api.sumsub.com/*' => Http::sequence()->push([], 401)->push([], 404)]);
    $sumsub = app(SumsubProvider::class);

    expect($sumsub->testConnection(['app_token' => 'x', 'secret_key' => 'y'], 'sandbox')->outcome)->toBe(Outcome::Failed)
        ->and($sumsub->testConnection(['app_token' => 'x', 'secret_key' => 'y'], 'sandbox')->outcome)->toBe(Outcome::Passed);
});

it('encrypts credentials, never sends secrets to the browser, and keeps them when left blank', function () {
    enableSmile();

    $raw = (string) DB::table('verification_providers')->where('key', 'smile_id')->value('credentials');
    expect($raw)->not->toContain('smile-secret-key-1234');

    $this->actingAs($this->admin)->get(route('admin.verification.providers'))->assertInertia(fn (Assert $p) => $p
        ->where('providers.0.key', 'smile_id')
        ->where('providers.0.fields.1.value', null)
        ->where('providers.0.fields.1.hint', '•••• 1234')
        ->where('providers.0.fields.0.value', '085'));

    $this->actingAs($this->admin)->put(route('admin.verification.providers.update', 'smile_id'), [
        'enabled' => true, 'mode' => 'live', 'credentials' => ['partner_id' => '085', 'api_key' => ''],
    ]);
    expect(ProviderSetting::where('key', 'smile_id')->first()->credentials['api_key'])->toBe('smile-secret-key-1234');
});

it('only routes checks to enabled, ready providers that support them', function () {
    $this->actingAs($this->admin)->put(route('admin.verification.providers.update', 'verifyid'), ['enabled' => true, 'mode' => 'sandbox'])
        ->assertSessionHasErrors('provider');

    $this->actingAs($this->admin)->put(route('admin.verification.routes'), ['routes' => ['sa_identity' => 'smile_id']])->assertSessionHasErrors('routes.sa_identity');

    enableSmile();
    $this->actingAs($this->admin)->put(route('admin.verification.routes'), ['routes' => ['sa_identity' => 'smile_id', 'passport_identity' => 'manual']])->assertSessionHasNoErrors();

    $registry = app(ProviderRegistry::class);
    expect($registry->forCheck(CheckType::SaIdentity)?->key())->toBe('smile_id')
        ->and($registry->forCheck(CheckType::PassportIdentity))->toBeNull();

    ProviderSetting::where('key', 'smile_id')->update(['enabled' => false]);
    expect($registry->forCheck(CheckType::SaIdentity))->toBeNull(); // falls back to manual review
});

it('accepts only correctly signed webhooks and processes each event once', function () {
    enableSmile();
    $buyer = User::factory()->create(['type' => 'buyer']);
    $v = Verification::create(['user_id' => $buyer->id, 'status' => 'submitted', 'document_type' => 'sa_id']);
    $signed = SmileIdProvider::sign('085', 'smile-secret-key-1234', '2026-10-24T08:00:00.000Z');
    $payload = ['SmileJobID' => 'J9', 'PartnerParams' => ['job_id' => $v->ulid], 'Actions' => ['Verify_ID_Number' => 'Verified', 'Names' => 'Exact Match']];

    $this->postJson(route('webhooks.verification', 'smile_id'), $payload + ['timestamp' => '2026-10-24T08:00:00.000Z', 'signature' => 'forged'])->assertStatus(401);
    expect($v->fresh()->status)->toBe('submitted');

    $this->postJson(route('webhooks.verification', 'smile_id'), $payload + $signed)->assertOk();
    $this->postJson(route('webhooks.verification', 'smile_id'), $payload + $signed)->assertOk()->assertJson(['duplicate' => true]);

    expect($v->fresh()->status)->toBe('verified')->and($v->fresh()->expires_at)->not->toBeNull()
        ->and(DB::table('verification_events')->count())->toBe(1);
});

it('verifies Sumsub webhook digests and maps review answers', function () {
    $this->actingAs($this->admin)->put(route('admin.verification.providers.update', 'sumsub'), [
        'enabled' => true, 'mode' => 'sandbox', 'credentials' => ['app_token' => 't', 'secret_key' => 's', 'level_name' => 'l', 'webhook_secret' => 'whsec'],
    ]);
    $buyer = User::factory()->create(['type' => 'buyer']);
    $v = Verification::create(['user_id' => $buyer->id, 'status' => 'submitted', 'document_type' => 'passport']);
    $body = json_encode(['type' => 'applicantReviewed', 'applicantId' => 'A1', 'externalUserId' => $v->ulid, 'createdAtMs' => '1', 'reviewResult' => ['reviewAnswer' => 'RED', 'reviewRejectType' => 'RETRY']]);

    $this->call('POST', route('webhooks.verification', 'sumsub'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYLOAD_DIGEST' => 'bad'], $body)->assertStatus(401);
    $this->call('POST', route('webhooks.verification', 'sumsub'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYLOAD_DIGEST' => hash_hmac('sha256', $body, 'whsec')], $body)->assertOk();

    expect($v->fresh()->status)->toBe('review');
});

it('is limited to staff with verification.providers', function () {
    $kyc = User::factory()->staff(SystemRole::KycOfficer)->create();

    $this->actingAs($kyc)->get(route('admin.verification.providers'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.verification.providers'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('providers', 3)->where('providers.1.ready', false)
            ->where('providers.0.mode', 'sandbox')->where('providers.0.enabled', false)); // fresh providers default to sandbox, off
});
