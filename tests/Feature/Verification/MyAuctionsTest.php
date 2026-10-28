<?php

use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Bidding\Models\MaxBid;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->buyer = User::factory()->create(['type' => 'buyer']);
});

function myListing(string $slug, ListingStatus $status = ListingStatus::Live): Listing
{
    return Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => ucfirst($slug), 'slug' => $slug,
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => $status, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
}

function myApproval(Listing $l, User $u, string $status = 'approved', ?string $number = '1234', bool $paid = true): BidderApproval
{
    $a = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u->id, 'status' => $status, 'bidder_number' => $status === 'approved' ? $number : null]);
    if ($paid) {
        Deposit::create(['listing_id' => $l->id, 'user_id' => $u->id, 'bidder_approval_id' => $a->id, 'amount' => 5000000, 'reference' => 'TDG-D-'.$number.'-ABCDE', 'status' => 'received']);
    }

    return $a;
}

function bid(Listing $l, BidderApproval $a, int $amount, ?int $max = null): void
{
    Bid::create(['listing_id' => $l->id, 'bidder_approval_id' => $a->id, 'bidder_number' => $a->bidder_number, 'amount' => $amount, 'kind' => 'manual', 'placed_at' => now()]);
    if ($max !== null) {
        MaxBid::create(['listing_id' => $l->id, 'bidder_approval_id' => $a->id, 'amount' => $max, 'set_at' => now()]);
    }
}

it('shows each auction at its stage with the next action', function () {
    myApproval(myListing('reviewing'), $this->buyer, 'pending', null, false);
    $dep = myListing('deposit-due');
    myApproval($dep, $this->buyer, 'approved', '2001', false);
    myApproval(myListing('opens-later', ListingStatus::Scheduled), $this->buyer, 'approved', '2002');

    $this->actingAs($this->buyer)->get(route('account.approvals'))->assertOk()->assertInertia(function (Assert $p) use ($dep) {
        $rows = collect($p->toArray()['props']['approvals'])->keyBy('listing');
        expect($rows['Reviewing']['stage'])->toBe('review')
            ->and($rows['Deposit-due']['stage'])->toBe('deposit')
            ->and($rows['Deposit-due']['depositUrl'])->toBe(route('deposits.show', $dep))
            ->and($rows['Opens-later']['stage'])->toBe('waiting');
    });
});

it('shows the highest bid, your bid, your maximum and whether you’re leading', function () {
    $l = myListing('family-home');
    $mine = myApproval($l, $this->buyer, 'approved', '1111');
    $rival = myApproval($l, User::factory()->create(['type' => 'buyer']), 'approved', '2222');
    bid($l, $mine, 1000000, 1200000);
    $state = BidEngine::state($l);
    $state->forceFill(['current_amount' => 1000000, 'leader_approval_id' => $mine->id, 'bid_count' => 1])->save();

    $this->actingAs($this->buyer)->get(route('account.approvals'))->assertInertia(fn (Assert $p) => $p
        ->where('approvals.0.stage', 'bidding')->where('approvals.0.highest', 1000000)->where('approvals.0.myBid', 1000000)
        ->where('approvals.0.myMaximum', 1200000)->where('approvals.0.leading', true));

    bid($l, $rival, 1300000);
    $state->forceFill(['current_amount' => 1300000, 'leader_approval_id' => $rival->id, 'bid_count' => 2])->save();
    $this->actingAs($this->buyer)->get(route('account.approvals'))->assertInertia(fn (Assert $p) => $p
        ->where('approvals.0.highest', 1300000)->where('approvals.0.leading', false));
});

it('shows the result once the auction closes', function () {
    $l = myListing('sold-home', ListingStatus::Sold);
    $mine = myApproval($l, $this->buyer, 'approved', '3333');
    DB::table('auction_results')->insert(['listing_id' => $l->id, 'kind' => 'open', 'outcome' => 'sold', 'winner_approval_id' => $mine->id, 'winning_amount' => 1500000, 'bidders' => 2, 'closed_at' => now()]);

    $this->actingAs($this->buyer)->get(route('account.approvals'))->assertInertia(fn (Assert $p) => $p
        ->where('approvals.0.stage', 'closed')->where('approvals.0.won', true));
});
