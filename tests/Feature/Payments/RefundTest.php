<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Actions\RefundService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\PaymentProviderSetting;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Models\Refund;
use App\Domains\Payments\Models\RefundBatch;
use App\Domains\Payments\Notifications\RefundPaid;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Payments\Support\Reconciliation;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->preparer = User::factory()->staff(SystemRole::Finance)->create();
    $this->approver = User::factory()->staff(SystemRole::Finance)->create();
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube']);
    BankAccount::create(['user_id' => $this->buyer->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => '62000000000', 'account_last4' => '0000',
        'account_type' => 'cheque', 'holder_name' => 'Naledi Dube', 'status' => 'verified', 'locked' => false]);
});

/** A received deposit of R 50 000 (or $cents), paid by EFT through the real money path. */
function heldDeposit(User $buyer, int $cents = 5000000, string $method = 'eft'): Deposit
{
    static $n = 0;
    $n++;
    $listing = Listing::create([
        'category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => "Home {$n}", 'slug' => "home-{$n}",
        'deal_type' => 'commission', 'seller_net_price' => 1000000, 'fee_bps' => 2000, 'minimum_price' => 1250000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
    ]);
    $approval = BidderApproval::create(['listing_id' => $listing->id, 'user_id' => $buyer->id, 'status' => 'approved', 'bidder_number' => (string) (4000 + $n)]);
    $deposit = Deposit::create(['listing_id' => $listing->id, 'user_id' => $buyer->id, 'bidder_approval_id' => $approval->id, 'amount' => 5000000,
        'reference' => DepositReference::make((string) (4000 + $n)), 'method' => $method, 'provider_ref' => $method === 'eft' ? null : "TX{$n}", 'status' => 'awaiting']);

    return app(DepositService::class)->receive($deposit, $cents, $method === 'eft' ? 'bank_import' : 'provider', "seed {$n}");
}

it('counts refund days in business days', function () {
    expect(RefundService::businessDaysFrom(Carbon::parse('2026-10-30'), 5)->toDateString())->toBe('2026-11-06') // Fri + 5 business days
        ->and(RefundService::businessDaysFrom(Carbon::parse('2026-10-31'), 1)->toDateString())->toBe('2026-11-02'); // Sat → Mon
});

it('schedules refunds only for held deposits, once', function () {
    $deposit = heldDeposit($this->buyer);
    expect(BankAccount::first()->locked)->toBeTrue();

    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.schedule'), ['reference' => $deposit->reference, 'reason' => 'unsuccessful'])->assertSessionHasNoErrors();
    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.schedule'), ['reference' => $deposit->reference, 'reason' => 'unsuccessful'])->assertSessionHasErrors('refund');
    expect($deposit->fresh()->status)->toBe('refund_scheduled');

    $this->actingAs($this->preparer)->delete(route('admin.finance.refunds.cancel', Refund::first()));
    expect($deposit->fresh()->status)->toBe('received')->and(Refund::first()->status)->toBe('cancelled');
});

it('runs the two-person EFT refund from batch to receipt and unlocks the account', function () {
    $deposit = heldDeposit($this->buyer, 5000500); // R 5 over-paid: the full balance is refunded
    app(RefundService::class)->schedule($deposit, 'unsuccessful', $this->preparer, today());
    $later = heldDeposit($this->buyer);
    app(RefundService::class)->schedule($later, 'withdrawn', $this->preparer, today()->addDays(3)); // not due yet

    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.prepare'))->assertSessionHasNoErrors();
    $batch = RefundBatch::firstOrFail();
    expect($batch->count)->toBe(1)->and($batch->total)->toBe(5000500);

    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.approve', $batch))->assertSessionHasErrors('batch'); // own batch
    $this->actingAs($this->approver)->get(route('admin.finance.refunds.bank-file', $batch))->assertStatus(422);              // not approved yet
    $this->actingAs($this->approver)->post(route('admin.finance.refunds.approve', $batch))->assertSessionHasNoErrors();
    expect($batch->fresh()->status)->toBe('approved');

    $csv = $this->actingAs($this->preparer)->get(route('admin.finance.refunds.bank-file', $batch))->streamedContent();
    expect($csv)->toContain('Naledi Dube')->toContain('62000000000')->toContain('50005.00')->toContain('REFUND '.$deposit->reference);

    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.paid', $batch))->assertSessionHasErrors('proof');
    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.paid', $batch), ['proof' => UploadedFile::fake()->create('fnb-confirmation.pdf', 30, 'application/pdf')])->assertSessionHasNoErrors();

    expect($batch->fresh()->status)->toBe('paid')->and($deposit->fresh()->status)->toBe('refunded')->and($deposit->fresh()->balance())->toBe(0)
        ->and(LedgerEntry::where('type', 'refunded')->sum('amount'))->toEqual(-5000500)
        ->and(Receipt::where('kind', 'refund')->count())->toBe(1)
        ->and(BankAccount::first()->locked)->toBeTrue(); // the second deposit is still held
    Notification::assertSentTo($this->buyer, RefundPaid::class);

    $this->travelTo(now()->addDays(4));
    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.prepare'));
    $second = RefundBatch::latest('id')->firstOrFail();
    $this->actingAs($this->approver)->post(route('admin.finance.refunds.approve', $second));
    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.paid', $second), ['proof' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')]);
    expect(BankAccount::first()->locked)->toBeFalse(); // nothing held any more
});

it('holds back EFT refunds without a verified, locked account', function () {
    $deposit = heldDeposit($this->buyer);
    BankAccount::query()->update(['status' => 'pending']);
    app(RefundService::class)->schedule($deposit, 'unsuccessful', $this->preparer, today());

    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.prepare'));

    expect(RefundBatch::count())->toBe(0)->and(Refund::first()->failure)->toContain('not verified and locked');
});

it('refunds escrowed deposits through TradeSafe on approval, and retries failures later', function () {
    PaymentProviderSetting::create(['key' => 'tradesafe', 'enabled' => true, 'mode' => 'sandbox', 'credentials' => ['client_id' => 'c', 'client_secret' => 's', 'party_token' => 'T', 'callback_secret' => 'x']]);
    $ok = heldDeposit($this->buyer, method: 'tradesafe');
    $bad = heldDeposit($this->buyer, method: 'tradesafe');
    foreach ([$ok, $bad] as $d) {
        app(RefundService::class)->schedule($d, 'unsuccessful', $this->preparer, today());
    }
    Http::fake([
        'auth.tradesafe.co.za/*' => Http::response(['access_token' => 't']),
        'api-developer.tradesafe.dev/*' => fn ($r) => Http::response(['data' => ['transactionCancel' => ['state' => data_get($r->data(), 'variables.id') === $ok->provider_ref ? 'REFUNDED' : 'CANCELLED']]]),
    ]);

    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.prepare'));
    $batch = RefundBatch::firstOrFail();
    $this->actingAs($this->approver)->post(route('admin.finance.refunds.approve', $batch))->assertSessionHasNoErrors();

    $batch->refresh();
    expect($ok->fresh()->status)->toBe('refunded')
        ->and($bad->fresh()->status)->toBe('refund_scheduled')
        ->and(Refund::where('deposit_id', $bad->id)->first()->status)->toBe('scheduled')
        ->and(Refund::where('deposit_id', $bad->id)->first()->failure)->toContain('Provider refund failed')
        ->and($batch->status)->toBe('paid')->and($batch->count)->toBe(1)->and($batch->total)->toBe(5000000);
});

it('reconciles to the cent and reports differences', function () {
    $deposit = heldDeposit($this->buyer);
    app(RefundService::class)->schedule($deposit, 'unsuccessful', $this->preparer, today());
    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.prepare'));
    $this->actingAs($this->approver)->post(route('admin.finance.refunds.approve', RefundBatch::first()));
    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.paid', RefundBatch::first()), ['proof' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')]);

    $r = Reconciliation::run();
    expect($r['totals'])->toBe(['received' => 5000000, 'refunded' => 5000000, 'held' => 0]);

    // bank_import ledger entries in this test have no statement lines, so the bank check flags them: that is the point
    expect($r['issues'])->toHaveCount(1)->and($r['issues'][0])->toContain('Bank statements show');

    LedgerEntry::create(['deposit_id' => $deposit->id, 'type' => 'adjustment', 'amount' => 100, 'source' => 'manual', 'created_at' => now()]);
    expect(collect(Reconciliation::run()['issues'])->contains(fn ($i) => str_contains($i, 'refunded but still holds R 1.00')))->toBeTrue();
});

it('limits refunds to finance', function () {
    $auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();

    $this->actingAs($auctioneer)->get(route('admin.finance.refunds.index'))->assertForbidden();
    $this->actingAs($this->preparer)->get(route('admin.finance.refunds.index', ['tab' => 'reconciliation']))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/finance/refunds')->where('reconciliation.balanced', true));
    $this->actingAs($this->preparer)->get(route('admin.finance.refunds.ledger'))->assertOk();
});

it('cancels a batch whose provider refunds all fail', function () {
    PaymentProviderSetting::create(['key' => 'tradesafe', 'enabled' => true, 'mode' => 'sandbox', 'credentials' => ['client_id' => 'c', 'client_secret' => 's', 'party_token' => 'T', 'callback_secret' => 'x']]);
    app(RefundService::class)->schedule(heldDeposit($this->buyer, method: 'tradesafe'), 'unsuccessful', $this->preparer, today());
    Http::fake(['auth.tradesafe.co.za/*' => Http::response(['access_token' => 't']), 'api-developer.tradesafe.dev/*' => Http::response([], 500)]);

    $this->actingAs($this->preparer)->post(route('admin.finance.refunds.prepare'));
    $this->actingAs($this->approver)->post(route('admin.finance.refunds.approve', RefundBatch::first()));

    expect(RefundBatch::first()->status)->toBe('cancelled')->and(RefundBatch::first()->count)->toBe(0)->and(Refund::first()->status)->toBe('scheduled');
});
