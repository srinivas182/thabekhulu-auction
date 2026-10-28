<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function queueListing(string $slug): Listing
{
    return Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => ucfirst($slug), 'slug' => $slug,
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
}

it('lists every waiting application across auctions, oldest first, with what is missing', function () {
    $a = queueListing('first-home');
    $b = queueListing('second-home');
    $older = BidderApproval::create(['listing_id' => $b->id, 'user_id' => User::factory()->create(['type' => 'buyer', 'first_name' => 'Older'])->id, 'status' => 'pending']);
    $older->forceFill(['created_at' => now()->subDays(3)])->save();
    BidderApproval::create(['listing_id' => $a->id, 'user_id' => User::factory()->create(['type' => 'buyer', 'first_name' => 'Newer'])->id, 'status' => 'pending']);
    BidderApproval::create(['listing_id' => $a->id, 'user_id' => User::factory()->create(['type' => 'buyer'])->id, 'status' => 'approved', 'bidder_number' => '1111']);

    $this->actingAs($this->admin)->get(route('admin.bidders.queue'))->assertOk()->assertInertia(fn (Assert $p) => $p
        ->has('applications', 2)
        ->where('applications.0.buyer', fn ($n) => str_starts_with($n, 'Older'))
        ->where('applications.0.waitingDays', 3)
        ->where('applications.0.ready', false)
        ->where('applications.0.missing', fn ($m) => count($m) > 0));

    $support = User::factory()->staff(SystemRole::Support)->create();
    $support->syncRoles([]);
    $support->syncPermissions([]);
    $this->actingAs($support)->get(route('admin.bidders.queue'))->assertForbidden();
});

it('shows applied, waiting, approved, bidders and the highest bid in the listings table', function () {
    $l = queueListing('busy-home');
    queueListing('quiet-home');
    $u = fn () => User::factory()->create(['type' => 'buyer'])->id;
    BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u(), 'status' => 'pending']);
    $one = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u(), 'status' => 'approved', 'bidder_number' => '1111']);
    $two = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u(), 'status' => 'approved', 'bidder_number' => '2222']);
    BidderApproval::create(['listing_id' => $l->id, 'user_id' => $u(), 'status' => 'approved', 'bidder_number' => '3333']);
    foreach ([[$one, 1000000], [$two, 1100000], [$one, 1200000]] as [$ap, $amount]) {
        Bid::create(['listing_id' => $l->id, 'bidder_approval_id' => $ap->id, 'bidder_number' => $ap->bidder_number, 'amount' => $amount, 'kind' => 'manual', 'placed_at' => now()]);
    }
    Bid::create(['listing_id' => $l->id, 'bidder_approval_id' => BidderApproval::where('bidder_number', '3333')->value('id'), 'bidder_number' => '3333', 'amount' => 900000, 'kind' => 'manual', 'placed_at' => now(), 'voided_at' => now()]);
    BidEngine::state($l)->forceFill(['current_amount' => 1200000])->save();

    $this->actingAs($this->admin)->get(route('admin.listings.index'))->assertInertia(function (Assert $p) {
        $rows = collect($p->toArray()['props']['listings']['data'])->keyBy('title');
        expect($rows['Busy-home'])->toMatchArray(['applied' => 4, 'pending' => 1, 'approved' => 3, 'bidders' => 2, 'highestBid' => 1200000])
            ->and($rows['Quiet-home'])->toMatchArray(['applied' => 0, 'pending' => 0, 'approved' => 0, 'bidders' => 0]);
    });
});
