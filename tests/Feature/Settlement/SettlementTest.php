<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Models\LedgerEntry;
use App\Domains\Payments\Models\Refund;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Settlement\Actions\SettlementService;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Settlement\Models\Suspension;
use App\Domains\Settlement\Notifications\SettlementNotice;
use App\Domains\Verification\Models\BankAccount;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->auctioneer = User::factory()->staff(SystemRole::Auctioneer)->create();
    $this->finance = User::factory()->staff(SystemRole::Finance)->create();
});

/** A closed auction: A wins at R1 200 000, B is runner-up at R1 100 000, C lost. Deposits R50 000 each. */
function settledAuction(string $terms = 'applied', string $category = 'homes'): array
{
    $l = Listing::create([
        'category_id' => Category::where('slug', $category)->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Family home', 'slug' => 'family-home-'.uniqid(),
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => $terms,
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addHour(),
    ]);
    $users = [];
    foreach (['A' => '1111', 'B' => '2222', 'C' => '3333'] as $name => $number) {
        $u = User::factory()->create(['type' => 'buyer', 'first_name' => $name, 'last_name' => 'Buyer']);
        $ap = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);
        Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
        Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
        BankAccount::create(['user_id' => $u->id, 'bank' => 'FNB', 'branch_code' => '250655', 'account_number' => (string) random_int(6200000000, 6299999999), 'account_last4' => '0000', 'account_type' => 'cheque', 'holder_name' => $name, 'status' => 'verified']);
        $d = Deposit::create(['listing_id' => $l->id, 'user_id' => $u->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => DepositReference::make($number), 'status' => 'awaiting']);
        app(DepositService::class)->receive($d, 5000000, 'bank_import', "seed {$number}");
        $users[$name] = $u;
    }
    $e = app(BidEngine::class);
    $e->place($l, $users['C'], 1000000);
    $e->place($l, $users['B'], 1100000);
    $e->place($l, $users['A'], 1200000);
    $l->update(['ends_at' => now()->subSecond()]);
    AuctionState::query()->where('listing_id', $l->id)->update(['ends_at' => now()->subSecond()]);
    test()->artisan('auctions:close');

    return [$l->fresh(), $users, SettlementCase::where('listing_id', $l->id)->firstOrFail()];
}

function depositOf(User $u): Deposit
{
    return Deposit::where('user_id', $u->id)->firstOrFail();
}

it('opens the winner’s settlement at close with deadlines from settings', function () {
    [, $u, $case] = settledAuction();

    expect($case->kind)->toBe('sale')->and($case->status)->toBe('open')->and($case->price)->toBe(1200000)
        ->and($case->approval->user_id)->toBe($u['A']->id)
        ->and($case->balance_due->toDateString())->toBe(today()->addDays(30)->toDateString())
        ->and($case->agreement_due->isWeekend())->toBeFalse();
});

it('completes only with every step, applying an applied deposit to the price', function () {
    [, $u, $case] = settledAuction('applied');
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.complete', $case))->assertSessionHasErrors('case');

    foreach (array_keys(SettlementCase::STEPS) as $step) {
        $this->actingAs($this->auctioneer)->post(route('admin.settlement.step', $case), ['step' => $step, 'done_on' => today()->toDateString()])->assertSessionHasNoErrors();
    }
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.complete', $case))->assertSessionHasNoErrors();

    expect($case->fresh()->status)->toBe('completed')->and(depositOf($u['A'])->status)->toBe('applied')->and(depositOf($u['A'])->balance())->toBe(0)
        ->and(LedgerEntry::where('type', 'applied')->sum('amount'))->toEqual(-5000000);
});

it('releases a security deposit for refund at completion', function () {
    [, $u, $case] = settledAuction('security');
    foreach (array_keys(SettlementCase::STEPS) as $step) {
        app(SettlementService::class)->recordStep($case, $step, today()->toDateString(), null, null, $this->auctioneer);
    }
    app(SettlementService::class)->complete($case, $this->auctioneer);

    expect(Refund::where('deposit_id', depositOf($u['A'])->id)->value('reason'))->toBe('security_released');
});

it('caps the forfeiture at the lowest of 10% of the price, the auction cost and the deposit held', function () {
    expect(SettlementService::cap(120000000, 3000000, 5000000))->toBe(3000000)    // auction cost lowest
        ->and(SettlementService::cap(120000000, 99000000, 5000000))->toBe(5000000) // deposit held lowest
        ->and(SettlementService::cap(30000000, 99000000, 5000000))->toBe(3000000)  // 10% of R300 000 lowest
        ->and(SettlementService::cap(120000000, 0, 5000000))->toBe(0);
});

it('runs a default: missed deadline, two people, capped forfeit, excess refund, suspension, runner-up offer', function () {
    [$l, $u, $case] = settledAuction();
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.default', $case), ['reason' => 'No signature', 'auction_cost' => 30000])->assertSessionHasErrors('default'); // nothing missed yet

    $this->travelTo($case->agreement_due->addDay());
    $this->actingAs($this->auctioneer)->post(route('admin.settlement.default', $case), ['reason' => 'No signature', 'auction_cost' => 30000])->assertSessionHasNoErrors();
    expect($case->fresh()->default->proposed_forfeit)->toBe(3000000);

    $this->actingAs($this->auctioneer)->post(route('admin.settlement.default.confirm', $case))->assertForbidden(); // auctioneers can't confirm
    $this->actingAs($this->finance)->post(route('admin.settlement.default.confirm', $case))->assertSessionHasNoErrors();

    $a = depositOf($u['A']);
    expect(LedgerEntry::where('deposit_id', $a->id)->where('type', 'forfeited')->sum('amount'))->toEqual(-3000000)
        ->and($a->balance())->toBe(2000000)
        ->and(Refund::where('deposit_id', $a->id)->value('reason'))->toBe('default_excess')
        ->and($case->fresh()->status)->toBe('defaulted')
        ->and(Suspension::activeFor($u['A']->id)?->until->toDateString())->toBe(today()->addMonths(12)->toDateString());

    $offer = SettlementCase::where('listing_id', $l->id)->where('kind', 'runner_up')->firstOrFail();
    expect($offer->status)->toBe('offered')->and($offer->price)->toBe(1100000)->and($offer->approval->user_id)->toBe($u['B']->id);
    Notification::assertSentTo($u['B'], SettlementNotice::class, fn ($n) => $n->kind === 'offer');
});

it('needs the security sign-off and a second person', function () {
    [, , $case] = settledAuction('security', 'mining');
    $this->travelTo($case->agreement_due->addDay());
    $both = User::factory()->staff(SystemRole::SuperAdmin)->create();

    $this->actingAs($both)->post(route('admin.settlement.default', $case), ['reason' => 'x', 'auction_cost' => 30000])->assertSessionHasErrors('security_signoff');
    $this->actingAs($both)->post(route('admin.settlement.default', $case), ['reason' => 'x', 'auction_cost' => 30000, 'security_signoff' => true])->assertSessionHasNoErrors();
    $this->actingAs($both)->post(route('admin.settlement.default.confirm', $case))->assertSessionHasErrors('default'); // declarer can't confirm
});

it('blocks a suspended buyer from applying and bidding', function () {
    [$l, $u] = settledAuction();
    Suspension::create(['user_id' => $u['C']->id, 'until' => today()->addMonths(12), 'reason' => 'Default']);
    $other = Listing::create(['category_id' => $l->category_id, 'city_id' => $l->city_id, 'title' => 'Other', 'slug' => 'other-'.uniqid(), 'deal_type' => 'commission',
        'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied', 'auction_type' => 'open',
        'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);

    $this->actingAs($u['C'])->post(route('auctions.apply', $other->ulid))->assertSessionHasErrors('apply');
    BidderApproval::create(['listing_id' => $other->id, 'user_id' => $u['C']->id, 'status' => 'approved', 'bidder_number' => '9999']);
    expect(fn () => app(BidEngine::class)->place($other, $u['C'], 1000000))->toThrow(ValidationException::class);
});

it('lets the runner-up accept (refund cancelled) or lets the offer expire', function () {
    [$l, $u, $case] = settledAuction();
    $this->travelTo($case->agreement_due->addDay());
    app(SettlementService::class)->declareDefault($case, 'No signature', 3000000, false, $this->auctioneer);
    app(SettlementService::class)->confirmDefault($case->fresh(), $this->finance);
    $offer = SettlementCase::where('kind', 'runner_up')->firstOrFail();

    $this->actingAs($u['C'])->post(route('account.purchases.accept', $offer))->assertForbidden();
    $this->actingAs($u['B'])->post(route('account.purchases.accept', $offer))->assertSessionHasNoErrors();

    expect($offer->fresh()->status)->toBe('open')->and($offer->fresh()->agreement_due)->not->toBeNull()
        ->and(depositOf($u['B'])->status)->toBe('received')
        ->and(Refund::where('deposit_id', depositOf($u['B'])->id)->where('status', 'scheduled')->exists())->toBeFalse();
});

it('expires an unanswered runner-up offer and reminds buyers of deadlines', function () {
    [, $u, $case] = settledAuction();
    $this->travelTo($case->agreement_due->subDays(2)->setTime(8, 0));
    $this->artisan('settlement:daily');
    Notification::assertSentTo($u['A'], SettlementNotice::class, fn ($n) => $n->kind === 'reminder');

    $this->travelTo($case->agreement_due->addDay());
    app(SettlementService::class)->declareDefault($case, 'x', 3000000, false, $this->auctioneer);
    app(SettlementService::class)->confirmDefault($case->fresh(), $this->finance);
    $this->travel(6)->days();
    $this->artisan('settlement:daily');

    expect(SettlementCase::where('kind', 'runner_up')->value('status'))->toBe('expired');
});

it('labels a sold Mining listing as preferred bidder, subject to approvals', function () {
    [$l] = settledAuction('security', 'mining');

    $this->get("/auctions/mining/emalahleni/{$l->slug}")->assertInertia(fn (Assert $p) => $p
        ->where('listing.status.label', 'Preferred bidder: subject to approvals')->where('auction.mining', true)->where('auction.outcome', 'sold'));
});

it('limits settlement pages to staff', function () {
    [, $u, $case] = settledAuction();

    $this->actingAs($u['A'])->get(route('admin.settlement.show', $case))->assertForbidden();
    $this->actingAs($this->finance)->get(route('admin.settlement.show', $case))->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.confirm', true)->where('can.declare', false));
    $this->actingAs($u['A'])->get(route('account.purchases'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('cases', 1));
});
