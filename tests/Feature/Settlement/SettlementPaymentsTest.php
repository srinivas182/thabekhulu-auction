<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Settlement\Actions\SettlementPayments;
use App\Domains\Settlement\Actions\SettlementService;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Models\SettlementPayment;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->listing = Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Sold, 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay()]);
    $this->buyer = User::factory()->create(['type' => 'buyer', 'first_name' => 'Thandi']);
    $this->approval = BidderApproval::create(['listing_id' => $this->listing->id, 'user_id' => $this->buyer->id, 'status' => 'approved', 'bidder_number' => '4821']);
    $this->deposit = Deposit::create(['listing_id' => $this->listing->id, 'user_id' => $this->buyer->id, 'bidder_approval_id' => $this->approval->id, 'amount' => 5000000,
        'reference' => DepositReference::make('4821'), 'status' => 'awaiting']);
    $this->case = app(SettlementService::class)->open($this->listing, $this->approval->id, 1500000);
    $this->financeA = User::factory()->staff(SystemRole::Finance)->create();
    $this->financeB = User::factory()->staff(SystemRole::Finance)->create();
});

function received(Deposit $d): void
{
    app(DepositService::class)->receive($d, 5000000, 'bank_import', 'test');
}

it('shows the winner the balance (price less deposit) with a TDG-B reference, and takes proof', function () {
    received($this->deposit);
    $this->actingAs($this->buyer)->get(route('account.purchases'))->assertInertia(fn (Assert $p) => $p
        ->where('cases.0.balance.price', 150000000)->where('cases.0.balance.deposit', 5000000)->where('cases.0.balance.due', 145000000)
        ->where('cases.0.balance.reference', fn ($r) => str_starts_with($r, 'TDG-B-4821-')));

    $this->actingAs($this->buyer)->post(route('account.purchases.balance-proof', $this->case), ['proof' => UploadedFile::fake()->create('pop.pdf', 80, 'application/pdf')])->assertSessionHasNoErrors();
    expect(SettlementPayment::firstOrFail()->proof_path)->not->toBeNull()->and(SettlementPayment::firstOrFail()->status)->toBe('awaiting');

    $stranger = User::factory()->create(['type' => 'buyer']);
    $this->actingAs($stranger)->post(route('account.purchases.balance-proof', $this->case), ['proof' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertForbidden();
});

it('confirms the balance from the bank statement and records Balance paid automatically', function () {
    received($this->deposit);
    $p = app(SettlementPayments::class)->forCase($this->case);
    $csv = "Date,Description,Amount\n2026/10/30,EFT CR ".strtolower(str_replace('-', ' ', $p->reference))." T MOKOENA,1450000.00\n";

    $this->actingAs($this->financeA)->post(route('admin.finance.import'), ['statement' => UploadedFile::fake()->createWithContent('s.csv', $csv)])->assertSessionHasNoErrors();

    expect($p->fresh()->status)->toBe('received')->and($p->fresh()->received)->toBe(145000000)
        ->and($this->case->fresh()->steps()->where('step', 'balance_paid')->exists())->toBeTrue();
});

it('needs a second finance user to confirm a payment by hand', function () {
    $this->actingAs($this->financeA)->post(route('admin.confirmations.store'), ['kind' => 'deposit', 'target' => $this->deposit->ulid, 'amount' => '50000.00',
        'bank_reference' => 'FNB statement 30 Oct', 'paid_on' => now()->toDateString()])->assertSessionHasNoErrors();
    $id = (int) DB::table('payment_confirmations')->value('id');

    $this->actingAs($this->financeA)->post(route('admin.confirmations.approve', $id))->assertSessionHasErrors('confirmation'); // not the same person
    expect($this->deposit->fresh()->status)->toBe('awaiting');

    $this->actingAs($this->financeB)->post(route('admin.confirmations.approve', $id))->assertSessionHasNoErrors();
    expect($this->deposit->fresh()->status)->toBe('received')
        ->and(DB::table('payment_confirmations')->value('status'))->toBe('approved');
});

it('lets the buyer upload the signed agreement for staff to accept or reject', function () {
    $this->actingAs($this->buyer)->post(route('account.purchases.agreement', $this->case), ['document' => UploadedFile::fake()->create('agreement.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();
    $upload = (int) DB::table('settlement_uploads')->value('id');
    $staff = User::factory()->staff(SystemRole::SuperAdmin)->create();

    $this->actingAs($staff)->post(route('admin.settlement-uploads.reject', $upload), ['reason' => 'Page 3 is not signed.'])->assertSessionHasNoErrors();
    $this->actingAs($this->buyer)->get(route('account.purchases'))->assertInertia(fn (Assert $p) => $p->where('cases.0.agreement.reason', 'Page 3 is not signed.'));

    $this->actingAs($this->buyer)->post(route('account.purchases.agreement', $this->case), ['document' => UploadedFile::fake()->create('agreement2.pdf', 200, 'application/pdf')]);
    $second = (int) DB::table('settlement_uploads')->where('status', 'pending')->value('id');
    $this->actingAs($staff)->post(route('admin.settlement-uploads.accept', $second))->assertSessionHasNoErrors();

    expect(SettlementCase::find($this->case->id)->steps()->where('step', 'agreement_signed')->exists())->toBeTrue();
});
