<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ComplianceItem;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\BankLine;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\Receipt;
use App\Domains\Payments\Notifications\DepositReceived;
use App\Domains\Quotes\Settings\QuotationSettings;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->finance = User::factory()->staff(SystemRole::Finance)->create();
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Naledi', 'last_name' => 'Dube']);
    $this->listing = Listing::create([
        'category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 1000000, 'fee_bps' => 2000, 'minimum_price' => 1250000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
    ]);
    $this->approval = BidderApproval::create(['listing_id' => $this->listing->id, 'user_id' => $this->buyer->id, 'status' => 'approved', 'bidder_number' => '4821']);
    BankAccount::create(['user_id' => $this->buyer->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => '62000000000', 'account_last4' => '0000', 'account_type' => 'cheque', 'holder_name' => 'Naledi Dube', 'status' => 'verified']);
});

function signAll(Listing $l): void
{
    foreach (ComplianceItem::cases() as $item) {
        $l->complianceItems()->firstOrCreate(['item' => $item->value], ['signed_off_by' => User::factory()->create()->id, 'signed_off_at' => now()]);
    }
}

function statement(string $body, string $name = 'statement.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $body);
}

function openDeposit(): Deposit
{
    test()->actingAs(test()->buyer)->post(route('deposits.open', test()->listing->ulid))->assertSessionHasNoErrors();

    return Deposit::firstOrFail();
}

it('refuses deposits until every compliance item is signed off', function () {
    $this->listing->complianceItems()->create(['item' => 'seller_authority', 'signed_off_by' => $this->finance->id, 'signed_off_at' => now()]);

    $this->actingAs($this->buyer)->post(route('deposits.open', $this->listing->ulid))->assertSessionHasErrors('deposit');
    expect(Deposit::count())->toBe(0);

    signAll($this->listing);
    $deposit = openDeposit();
    expect($deposit->amount)->toBe(5000000)->and($deposit->reference)->toStartWith('TDG-D-4821-')->and($deposit->status)->toBe('awaiting');

    $this->actingAs($this->buyer)->post(route('deposits.open', $this->listing->ulid));
    expect(Deposit::count())->toBe(1); // one deposit per approval
});

it('confirms an exact payment from a statement, issues a receipt and locks the refund account', function () {
    signAll($this->listing);
    $deposit = openDeposit();
    $csv = "Date,Description,Amount\n2026/10/26,EFT CR {$deposit->reference} N DUBE,50000.00\n";

    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement($csv)])->assertSessionHasNoErrors();

    $deposit->refresh();
    expect($deposit->status)->toBe('received')->and($deposit->balance())->toBe(5000000)
        ->and(Receipt::first()->number)->toBe('TDG-R-'.now('Africa/Johannesburg')->format('Y').'-0001')
        ->and(BankAccount::first()->locked)->toBeTrue(); // the received deposit is what unlocks bidding (tested in AdvanceAndProofTest)
    Notification::assertSentTo($this->buyer, DepositReceived::class);
});

it('never confirms short, over or unknown payments automatically', function () {
    signAll($this->listing);
    $deposit = openDeposit();
    $csv = "Date,Description,Amount\n2026/10/26,{$deposit->reference},20000.00\n2026/10/26,{$deposit->reference} again,90000.00\n2026/10/26,TDG-D-4821-AAAAA,50000.00\n2026/10/26,no reference at all,1.00\n";

    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement($csv)]);

    expect($deposit->fresh()->status)->toBe('awaiting')->and(LedgerEntry::count())->toBe(0)
        ->and(BankLine::pluck('status')->sort()->values()->all())->toBe(['over', 'short', 'unmatched', 'unmatched']);
});

it('ignores a statement or lines it has already seen', function () {
    signAll($this->listing);
    $deposit = openDeposit();
    $csv = "Date,Description,Amount\n2026/10/26,{$deposit->reference},50000.00\n";

    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement($csv)]);
    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement($csv)])->assertSessionHasErrors('statement');
    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement("Overlapping export\n".$csv, 'next-week.csv')])->assertSessionHasNoErrors();

    expect(LedgerEntry::count())->toBe(1)->and($deposit->fresh()->balance())->toBe(5000000);
});

it('lets finance apply queued lines with a note, adding up part payments', function () {
    signAll($this->listing);
    $deposit = openDeposit();
    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement("Date,Description,Amount\n2026/10/26,{$deposit->reference},20000.00\n2026/10/27,N Dube balance,30000.00\n")]);
    [$short, $unknown] = BankLine::orderBy('id')->get()->all();

    $this->actingAs($this->finance)->post(route('admin.finance.resolve', $short), ['action' => 'apply', 'reference' => $deposit->reference])->assertSessionHasErrors('note');
    $this->actingAs($this->finance)->post(route('admin.finance.resolve', $short), ['action' => 'apply', 'reference' => $deposit->reference, 'note' => 'First instalment'])->assertSessionHasNoErrors();
    expect($deposit->fresh()->status)->toBe('part_paid');

    $this->actingAs($this->finance)->post(route('admin.finance.resolve', $unknown), ['action' => 'apply', 'reference' => 'TDG-D-4821-ZZZZZ', 'note' => 'x'])->assertSessionHasErrors('reference');
    $this->actingAs($this->finance)->post(route('admin.finance.resolve', $unknown), ['action' => 'apply', 'reference' => $deposit->reference, 'note' => 'Buyer confirmed by phone; reference omitted'])->assertSessionHasNoErrors();

    expect($deposit->fresh()->status)->toBe('received')->and(Receipt::count())->toBe(1)->and(Receipt::first()->amount)->toBe(5000000);
});

it('keeps the ledger append-only', function () {
    signAll($this->listing);
    $deposit = openDeposit();
    $entry = LedgerEntry::create(['deposit_id' => $deposit->id, 'type' => 'received', 'amount' => 100, 'source' => 'manual', 'created_at' => now()]);

    expect(fn () => $entry->update(['amount' => 999]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('shows the buyer their payment details and lets only them download receipts', function () {
    signAll($this->listing);
    $deposit = openDeposit();
    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement("Date,Description,Amount\n2026/10/26,{$deposit->reference},50000.00\n")]);
    $receipt = Receipt::firstOrFail();

    $this->actingAs($this->buyer)->get(route('deposits.show', $this->listing->ulid))->assertInertia(fn (Assert $p) => $p
        ->component('account/deposit')->where('deposit.reference', $deposit->reference)->where('deposit.status', 'received')->has('deposit.receipts', 1));
    $this->actingAs($this->buyer)->get(route('receipts.download', $receipt->number))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs(User::factory()->create(['type' => 'buyer']))->get(route('receipts.download', $receipt->number))->assertForbidden();
});

it('limits finance tools by permission', function () {
    $kyc = User::factory()->staff(SystemRole::KycOfficer)->create();
    $auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create(); // may see deposits, not reconcile

    $this->actingAs($kyc)->get(route('admin.finance.index'))->assertForbidden();
    $this->actingAs($auctioneer)->get(route('admin.finance.index'))->assertOk();
    $this->actingAs($auctioneer)->post(route('admin.finance.import'), ['statement' => statement("Date,Description,Amount\n")])->assertForbidden();
    $this->actingAs($this->finance)->get(route('admin.finance.index'))->assertOk();
    $this->actingAs($this->finance)->get(route('admin.finance.settings'))->assertForbidden(); // settings.manage
});

it('states the correct deposit terms on the receipt', function (string $terms, string $expected, string $notExpected) {
    $this->listing->update(['deposit_terms' => $terms]);
    signAll($this->listing);
    $deposit = openDeposit();
    $this->actingAs($this->finance)->post(route('admin.finance.import'), ['statement' => statement("Date,Description,Amount\n2026/10/26,{$deposit->reference},50000.00\n")]);
    $receipt = Receipt::with(['deposit.listing', 'deposit.user'])->firstOrFail();

    $html = view('pdf.receipt', ['receipt' => $receipt, 'company' => app(QuotationSettings::class), 'tz' => 'Africa/Johannesburg'])->render();

    expect($html)->toContain($expected)->not->toContain($notExpected)->toContain('R 50 000.00');
})->with([
    'security deposit' => ['security', 'Refundable security deposit', 'Counts toward the purchase price'],
    'applied deposit' => ['applied', 'Counts toward the purchase price', 'Refundable security deposit'],
]);
