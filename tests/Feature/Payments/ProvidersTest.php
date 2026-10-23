<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ComplianceItem;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\PaymentProviderSetting;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

const TS_IP = '13.244.48.16';

/** What the fake TradeSafe API currently reports (Http::fake keeps its first match). */
final class FakeTradeSafeState
{
    /** @var array{state: string, txId: string, reference: string, value: float} */
    public static array $now = ['state' => 'FUNDS_RECEIVED', 'txId' => 'TX1', 'reference' => '', 'value' => 0.0];

    public static bool $installed = false;
}

beforeEach(function () {
    FakeTradeSafeState::$installed = false;
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube', 'mobile' => '+27825550000']);
    $this->listing = Listing::create([
        'category_id' => Category::where('slug', 'mining')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Coal right', 'slug' => 'coal-right',
        'deal_type' => 'commission', 'seller_net_price' => 1000000, 'fee_bps' => 2000, 'minimum_price' => 2300000, 'deposit_amount' => 1200000, 'deposit_terms' => 'security',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
    ]);
    foreach (ComplianceItem::cases() as $i) {
        $this->listing->complianceItems()->create(['item' => $i->value, 'signed_off_by' => $this->admin->id, 'signed_off_at' => now()]);
    }
    BidderApproval::create(['listing_id' => $this->listing->id, 'user_id' => $this->buyer->id, 'status' => 'approved', 'bidder_number' => '4821']);
    $this->actingAs($this->buyer)->post(route('deposits.open', $this->listing->ulid));
    $this->deposit = Deposit::firstOrFail();
});

function enableTradeSafe(): void
{
    test()->actingAs(test()->admin)->put(route('admin.finance.providers.update', 'tradesafe'), [
        'enabled' => true, 'mode' => 'sandbox',
        'credentials' => ['client_id' => 'cid', 'client_secret' => 'csecret-1234', 'party_token' => 'THABEKHULU', 'callback_secret' => 'cb-secret-xyz', 'graphql_url' => ''],
    ])->assertSessionHasNoErrors();
    test()->actingAs(test()->admin)->put(route('admin.finance.providers.deposit'), ['deposit_provider' => 'tradesafe'])->assertSessionHasNoErrors();
}

/**
 * TradeSafe fake: OAuth plus GraphQL answers chosen by operation. Http::fake
 * keeps the first match, so the reported state lives on the test and each
 * call to fakeTradeSafe() changes it.
 */
function fakeTradeSafe(string $state = 'FUNDS_RECEIVED', string $txId = 'TX1', string $reference = '', float $value = 1200000.00): void
{
    FakeTradeSafeState::$now = compact('state', 'txId', 'reference', 'value');
    if (FakeTradeSafeState::$installed) {
        return;
    }
    FakeTradeSafeState::$installed = true;

    Http::fake([
        'auth.tradesafe.co.za/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'api-developer.tradesafe.dev/*' => function (HttpRequest $r) {
            $q = (string) $r['query'];
            $t = FakeTradeSafeState::$now;

            return Http::response(['data' => match (true) {
                str_contains($q, 'tokenCreate') => ['tokenCreate' => ['id' => 'BUYERTOKEN']],
                str_contains($q, 'transactionCreate') => ['transactionCreate' => ['id' => $t['txId']]],
                str_contains($q, 'checkoutLink') => ['checkoutLink' => 'https://pay-sandbox.tradesafe.dev/checkout/abc'],
                str_contains($q, 'apiProfile') => ['apiProfile' => ['organizations' => [['name' => 'Thabekhulu']]]],
                default => ['transaction' => ['id' => $t['txId'], 'reference' => $t['reference'], 'state' => $t['state'], 'allocations' => [['value' => $t['value']]]]],
            }]);
        },
    ]);
}

function callback(string $secret = 'cb-secret-xyz', string $ip = TS_IP, string $txId = 'TX1')
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('webhooks.payments.tradesafe', $secret), ['data' => ['id' => $txId, 'state' => 'FUNDS_RECEIVED', 'balance' => '99999999']]);
}

it('creates the escrow transaction with our reference and sends the buyer to TradeSafe', function () {
    enableTradeSafe();
    fakeTradeSafe(reference: $this->deposit->reference);

    $this->actingAs($this->buyer)->post(route('deposits.pay', $this->listing->ulid), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://pay-sandbox.tradesafe.dev/checkout/abc');

    expect($this->deposit->fresh()->provider_ref)->toBe('TX1')->and($this->deposit->fresh()->method)->toBe('tradesafe');
    Http::assertSent(fn (HttpRequest $r) => isset($r['query']) && str_contains((string) $r['query'], 'transactionCreate')
        && data_get($r->data(), 'variables.input.reference') === $this->deposit->reference
        && data_get($r->data(), 'variables.input.allocations.create.0.value') === 1200000.0
        && data_get($r->data(), 'variables.input.parties.create.1') === ['token' => 'THABEKHULU', 'role' => 'SELLER']);
    Http::assertSent(fn (HttpRequest $r) => isset($r['query']) && str_contains((string) $r['query'], 'paymentMethods: [EFT, OZOW]'));
});

it('records money only when the secret, the address and TradeSafe’s own API all agree', function () {
    enableTradeSafe();
    fakeTradeSafe(reference: $this->deposit->reference);
    $this->actingAs($this->buyer)->post(route('deposits.pay', $this->listing->ulid));

    callback(secret: 'wrong')->assertOk();
    callback(ip: '203.0.113.9')->assertOk();
    expect(LedgerEntry::count())->toBe(0);

    fakeTradeSafe(state: 'CREATED', reference: $this->deposit->reference);
    callback()->assertOk();
    expect(LedgerEntry::count())->toBe(0);

    fakeTradeSafe(reference: $this->deposit->reference);
    callback()->assertOk();
    callback()->assertOk()->assertJson(['duplicate' => true]);

    $d = $this->deposit->fresh();
    expect($d->status)->toBe('received')->and($d->balance())->toBe(120000000) // from the API, not the callback's "balance"
        ->and(LedgerEntry::count())->toBe(1);
});

it('ignores a confirmed TradeSafe transaction that is not this deposit’s', function () {
    enableTradeSafe();
    fakeTradeSafe(reference: $this->deposit->reference);
    $this->actingAs($this->buyer)->post(route('deposits.pay', $this->listing->ulid));

    fakeTradeSafe(txId: 'OTHER', reference: $this->deposit->reference);
    callback(txId: 'OTHER')->assertOk();

    expect(LedgerEntry::count())->toBe(0);
});

it('falls back to EFT when TradeSafe is unavailable', function () {
    enableTradeSafe();
    Http::fake(['*' => Http::response([], 500)]);

    $this->actingAs($this->buyer)->from(route('deposits.show', $this->listing->ulid))->post(route('deposits.pay', $this->listing->ulid))->assertSessionHasErrors('pay');
    expect($this->deposit->fresh()->provider_ref)->toBeNull();
});

it('verifies Paystack signatures and charges with Paystack', function () {
    $this->actingAs($this->admin)->put(route('admin.finance.providers.update', 'paystack'), ['enabled' => true, 'mode' => 'sandbox', 'credentials' => ['secret_key' => 'sk_test_abc']]);
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::sequence()
        ->push(['data' => ['status' => 'failed', 'currency' => 'ZAR', 'amount' => 120000000, 'metadata' => ['deposit_reference' => $this->deposit->reference]]])
        ->push(['data' => ['status' => 'success', 'currency' => 'ZAR', 'amount' => 120000000, 'metadata' => ['deposit_reference' => $this->deposit->reference]]])]);
    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $this->deposit->reference.'-101500']]);
    $send = fn (string $sig) => $this->call('POST', route('webhooks.payments', 'paystack'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $sig], $body);

    $send('forged')->assertOk();
    $send(hash_hmac('sha512', $body, 'sk_test_abc'))->assertOk(); // Paystack says failed
    expect(LedgerEntry::count())->toBe(0);

    $send(hash_hmac('sha512', $body, 'sk_test_abc'))->assertOk();
    expect($this->deposit->fresh()->status)->toBe('received');
});

it('keeps keys encrypted, Ozow off, and deposit routing to usable providers only', function () {
    $this->actingAs($this->admin)->put(route('admin.finance.providers.deposit'), ['deposit_provider' => 'tradesafe'])->assertSessionHasErrors('deposit_provider');
    $this->actingAs($this->admin)->put(route('admin.finance.providers.update', 'ozow'), ['enabled' => true, 'mode' => 'sandbox'])->assertSessionHasErrors('provider');

    enableTradeSafe();
    expect((string) DB::table('payment_providers')->where('key', 'tradesafe')->value('credentials'))->not->toContain('csecret-1234')
        ->and(app(PaymentSettings::class)->deposit_provider)->toBe('tradesafe');

    PaymentProviderSetting::where('key', 'tradesafe')->update(['enabled' => false]);
    $this->actingAs($this->buyer)->get(route('deposits.show', $this->listing->ulid))->assertInertia(fn ($p) => $p->where('escrow', null));
});

it('limits provider settings to staff with payments.providers', function () {
    $finance = User::factory()->staff(SystemRole::Finance)->create();

    $this->actingAs($finance)->get(route('admin.finance.providers.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.finance.providers.index'))->assertOk()->assertInertia(fn ($p) => $p->has('providers', 3)->where('providers.1.ready', false));
});
