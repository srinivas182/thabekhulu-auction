<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Bidding\Models\MaxBid;
use App\Domains\Bidding\Notifications\BidCode;
use App\Domains\Bidding\Support\Increments;
use App\Domains\Bidding\Support\StepUp;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\DepositReference;
use App\Domains\Settings\Settings\AuctionSettings;
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
    $this->listing = Listing::create([
        'category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3),
    ]);
    [$this->a, $this->b, $this->c] = [bidder('Alice', 'Mokoena', '1111'), bidder('Bongani', 'Zulu', '2222'), bidder('Carla', 'Naidoo', '3333')];
    $this->engine = app(BidEngine::class);
});

/** A buyer approved for the test listing, deposit received, verified and screened. */
function bidder(string $first, string $last, string $number, bool $paid = true): User
{
    $u = User::factory()->create(['type' => 'buyer', 'first_name' => $first, 'last_name' => $last]);
    $approval = BidderApproval::create(['listing_id' => test()->listing->id, 'user_id' => $u->id, 'status' => 'approved', 'bidder_number' => $number]);
    Verification::create(['user_id' => $u->id, 'status' => 'verified', 'document_type' => 'sa_id', 'verified_at' => now(), 'expires_at' => now()->addYear()]);
    Screening::create(['user_id' => $u->id, 'result' => 'clear', 'lists' => 'FIC TFS', 'screened_at' => now()]);
    Deposit::create(['listing_id' => test()->listing->id, 'user_id' => $u->id, 'bidder_approval_id' => $approval->id, 'amount' => 5000000,
        'reference' => DepositReference::make($number), 'status' => $paid ? 'received' : 'awaiting']);

    return $u;
}

function state(): AuctionState
{
    return AuctionState::findOrFail(test()->listing->id);
}

function leaderNumber(): ?string
{
    return BidderApproval::whereKey(state()->leader_approval_id)->value('bidder_number');
}

function bidErrors(Closure $f): array
{
    try {
        $f();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('enforces the minimum and the increment bands', function () {
    expect(bidErrors(fn () => $this->engine->place($this->listing, $this->a, 999999)))->toHaveKey('amount');

    $this->engine->place($this->listing, $this->a, 1000000);
    // At R1 000 000 the R1m–R5m band applies: next bid R1 025 000.
    expect(bidErrors(fn () => $this->engine->place($this->listing, $this->b, 1010000)))->toHaveKey('amount');
    $this->engine->place($this->listing, $this->b, 1025000);

    expect(state()->current_amount)->toBe(1025000)->and(leaderNumber())->toBe('2222');
});

it('bids for a maximum only as much as needed and lets a higher maximum overtake', function () {
    $this->engine->place($this->listing, $this->a, 1000000, 1200000);
    $this->engine->place($this->listing, $this->b, 1025000);

    expect(state()->current_amount)->toBe(1050000)->and(leaderNumber())->toBe('1111')
        ->and(Bid::orderBy('id')->get()->map(fn ($b) => [$b->bidder_number, $b->amount, $b->kind])->all())
        ->toBe([['1111', 1000000, 'manual'], ['2222', 1025000, 'manual'], ['1111', 1050000, 'proxy']]);

    $this->engine->place($this->listing, $this->b, 1300000);
    expect(state()->current_amount)->toBe(1300000)->and(leaderNumber())->toBe('2222')
        ->and(Bid::orderByDesc('id')->limit(2)->get()->map(fn ($b) => [$b->bidder_number, $b->amount])->reverse()->values()->all())
        ->toBe([['1111', 1200000], ['2222', 1300000]]);
});

it('gives a tie between equal maximums to the earlier one', function () {
    $this->engine->place($this->listing, $this->a, 1000000, 1200000);
    $this->travel(1)->seconds();
    $r = $this->engine->place($this->listing, $this->b, 1025000, 1200000);

    expect($r['leading'])->toBeFalse()->and(state()->current_amount)->toBe(1200000)->and(leaderNumber())->toBe('1111');
});

it('stops the leader bidding against themselves but lets them raise their maximum', function () {
    $this->engine->place($this->listing, $this->a, 1000000, 1100000);

    expect(bidErrors(fn () => $this->engine->place($this->listing, $this->a, 1025000)))->toHaveKey('maximum');
    $this->engine->place($this->listing, $this->a, 1025000, 1500000);
    expect(state()->current_amount)->toBe(1000000)
        ->and(MaxBid::first()->value())->toBe(1500000);

    $this->engine->place($this->listing, $this->b, 1400000);
    expect(leaderNumber())->toBe('1111')->and(state()->current_amount)->toBe(1425000);
});

it('only lets eligible buyers bid', function () {
    $unpaid = bidder('Dan', 'Pillay', '4444', paid: false);
    $stranger = User::factory()->create(['type' => 'buyer']);
    $staff = User::factory()->staff(SystemRole::Auctioneer)->create();
    $stale = bidder('Eve', 'Botha', '5555');
    Screening::where('user_id', $stale->id)->update(['screened_at' => now()->subDays(31)]);

    foreach ([$unpaid, $stranger, $staff, $stale] as $u) {
        expect(bidErrors(fn () => $this->engine->place($this->listing, $u, 1000000)))->toHaveKey('bid');
    }

    $this->listing->update(['auction_type' => 'sealed']);
    expect(bidErrors(fn () => $this->engine->place($this->listing->fresh(), $this->a, 1000000)))->toHaveKey('bid');
});

it('extends the close for bids in the soft-close window only', function () {
    BidEngine::state($this->listing)->update(['ends_at' => now()->addMinutes(30)]);
    $this->engine->place($this->listing, $this->a, 1000000);
    expect(state()->extensions)->toBe(0);

    state()->update(['ends_at' => now()->addMinutes(3)]);
    $this->engine->place($this->listing, $this->b, 1025000);
    expect(state()->extensions)->toBe(1)->and(abs(state()->ends_at->diffInSeconds(now()->addMinutes(5))))->toBeLessThan(2);

    state()->update(['ends_at' => now()->subSecond()]);
    expect(bidErrors(fn () => $this->engine->place($this->listing, $this->c, 1050000)))->toHaveKey('bid');
});

it('asks for an emailed code on large bids, once', function () {
    $rules = app(AuctionSettings::class);
    $rules->step_up_mode = 'threshold';
    $rules->save();
    // Threshold = min(setting, 2 × minimum) = R2 000 000 here.
    $this->actingAs($this->a)->post(route('auctions.bid', $this->listing->ulid), ['amount' => 2000000])->assertSessionHasErrors('otp');
    $code = null;
    Notification::assertSentTo($this->a, BidCode::class, function (BidCode $n) use (&$code) {
        $code = $n->code;

        return true;
    });
    expect(Bid::count())->toBe(0);

    $this->actingAs($this->a)->post(route('auctions.bid', $this->listing->ulid), ['amount' => 2000000, 'otp' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('otp');
    $this->actingAs($this->a)->post(route('auctions.bid', $this->listing->ulid), ['amount' => 2000000, 'otp' => $code])->assertSessionHasNoErrors();
    expect(Bid::count())->toBe(1);

    $this->actingAs($this->a)->post(route('auctions.bid', $this->listing->ulid), ['amount' => 2000000, 'maximum' => 3000000, 'otp' => $code])->assertSessionHasErrors('otp'); // single use
});

it('never shows names or maximums publicly', function () {
    $this->engine->place($this->listing, $this->a, 1000000, 1300000);
    $this->engine->place($this->listing, $this->b, 1025000);

    $this->get("/auctions/homes/emalahleni/{$this->listing->slug}")->assertInertia(function (Assert $p) {
        $p->where('auction.current', 1050000)->has('auction.recent', 3);
        $json = json_encode($p->toArray()['props']['auction']);
        expect($json)->not->toContain('Mokoena')->not->toContain('Zulu')->not->toContain('1300000');
    });
    $this->actingAs($this->b)->get("/auctions/homes/emalahleni/{$this->listing->slug}")->assertInertia(fn (Assert $p) => $p->where('auction.mine.leading', false)->where('auction.mine.hasBid', true));
});

it('lets staff void a bid made in error and continues from the previous valid bid', function () {
    $staff = User::factory()->staff(SystemRole::Auctioneer)->create();
    $this->engine->place($this->listing, $this->a, 1000000);
    $this->engine->place($this->listing, $this->b, 1500000, 1600000);
    $wrong = Bid::where('amount', 1500000)->firstOrFail();

    $this->actingAs($staff)->post(route('admin.bids.void', $wrong), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($staff)->post(route('admin.bids.void', $wrong), ['reason' => 'Bidder typed an extra zero; confirmed by phone.'])->assertSessionHasNoErrors();

    expect(state()->current_amount)->toBe(1000000)->and(leaderNumber())->toBe('1111')
        ->and(MaxBid::whereNotNull('voided_at')->count())->toBe(1)
        ->and(fn () => $wrong->fresh()->update(['amount' => 1]))->toThrow(LogicException::class);
});

it('pauses bidding and gives the paused time back on resume', function () {
    $staff = User::factory()->staff(SystemRole::Auctioneer)->create();
    $endsBefore = BidEngine::state($this->listing)->ends_at;

    $this->actingAs($staff)->post(route('admin.listings.auction.pause', $this->listing));
    expect(bidErrors(fn () => $this->engine->place($this->listing, $this->a, 1000000)))->toHaveKey('bid');

    $this->travel(10)->minutes();
    $this->actingAs($staff)->post(route('admin.listings.auction.pause', $this->listing));
    expect(state()->paused_at)->toBeNull()->and((int) $endsBefore->diffInMinutes(state()->ends_at))->toBe(10);
});

it('exports the bidders’ record for staff only', function () {
    $staff = User::factory()->staff(SystemRole::Auctioneer)->create();

    $csv = $this->actingAs($staff)->get(route('admin.listings.bidders-record', $this->listing))->assertOk()->streamedContent();
    expect($csv)->toContain('1111')->toContain('Alice Mokoena')->toContain('Self');

    $this->actingAs($this->a)->get(route('admin.listings.bidders-record', $this->listing))->assertForbidden();
});

it('asks for a code by the chosen mode: off, first bid in each auction, or every bid', function () {
    $rules = app(AuctionSettings::class);
    $listing = test()->listing;
    $buyer = bidder('Mode', 'Tester', '4040');

    $rules->step_up_mode = 'off';
    $rules->save();
    expect(StepUp::requiredFrom($listing, $buyer))->toBeNull();

    $rules->step_up_mode = 'every';
    $rules->save();
    expect(StepUp::requiredFrom($listing, $buyer))->toBe(0);

    $rules->step_up_mode = 'first';
    $rules->save();
    expect(StepUp::requiredFrom($listing, $buyer))->toBe(0);       // no bid yet: code needed
    app(BidEngine::class)->place($listing, $buyer, (int) $listing->minimum_price);
    expect(StepUp::requiredFrom($listing, $buyer))->toBeNull();    // has bid: later bids need none
});

it('saves the auction rules, rejecting increment bands out of order', function () {
    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $rules = ['step_up_mode' => 'first', 'step_up_threshold' => 2000000, 'auto_bid_enabled' => true, 'soft_close_enabled' => true, 'soft_close_window_minutes' => 3,
        'soft_close_extension_minutes' => 3, 'closing_soon_hours' => 24];

    $this->actingAs($admin)->put(route('admin.settings.auction-rules.update'), $rules + ['increment_bands' => [['upTo' => 5000000, 'step' => 25000], ['upTo' => 1000000, 'step' => 10000], ['upTo' => null, 'step' => 50000]]])
        ->assertSessionHasErrors('increment_bands');
    $this->actingAs($admin)->put(route('admin.settings.auction-rules.update'), $rules + ['increment_bands' => [['upTo' => 2000000, 'step' => 20000], ['upTo' => null, 'step' => 40000]]])
        ->assertSessionHasNoErrors();

    $saved = app(AuctionSettings::class)->refresh();
    expect($saved->step_up_mode)->toBe('first')->and($saved->increment_bands)->toBe([[2000000, 20000], [null, 40000]])
        ->and(Increments::at(1500000))->toBe(20000);
    $this->actingAs(User::factory()->staff(SystemRole::Finance)->create())->get(route('admin.settings.auction-rules'))->assertForbidden();
});

it('keeps automatic bidding on by default; switched off, refuses new maximums but honours existing ones', function () {
    $rules = app(AuctionSettings::class);
    expect($rules->auto_bid_enabled)->toBeTrue();
    $listing = test()->listing;
    $a = bidder('Auto', 'Holder', '5151');
    $b = bidder('Manual', 'Bidder', '5252');
    $engine = app(BidEngine::class);
    $engine->place($listing, $a, 1000000, 1200000);                            // set while switched on

    $rules->auto_bid_enabled = false;
    $rules->save();

    $this->actingAs($b)->post(route('auctions.bid', $listing->ulid), ['amount' => 1025000, 'maximum' => 1500000])->assertSessionHasErrors('maximum');
    $this->actingAs($b)->post(route('auctions.bid', $listing->ulid), ['amount' => 1025000])->assertSessionHasNoErrors();

    $state = BidEngine::state($listing->fresh());
    expect(BidderApproval::whereKey($state->leader_approval_id)->value('bidder_number'))->toBe('5151')    // A's existing maximum still defends
        ->and($state->current_amount)->toBe(1050000);
});
