<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
});

function dashListing(string $slug, ListingStatus $status): Listing
{
    return Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => ucfirst($slug), 'slug' => $slug,
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => $status, 'starts_at' => now()->subDay(), 'ends_at' => now()->addHours(5)]);
}

it('shows what needs attention and the key figures', function () {
    $live = dashListing('live-home', ListingStatus::Live);
    dashListing('offer-home', ListingStatus::UnderOffer);
    $buyer = User::factory()->create(['type' => 'buyer']);
    $old = BidderApproval::create(['listing_id' => $live->id, 'user_id' => $buyer->id, 'status' => 'pending']);
    $old->forceFill(['created_at' => now()->subDays(3)])->save();
    $paid = BidderApproval::create(['listing_id' => $live->id, 'user_id' => User::factory()->create(['type' => 'buyer'])->id, 'status' => 'approved', 'bidder_number' => '1111']);
    Deposit::create(['listing_id' => $live->id, 'user_id' => $paid->user_id, 'bidder_approval_id' => $paid->id, 'amount' => 5000000, 'reference' => 'TDG-D-1111-AAAAA', 'status' => 'received']);

    $this->actingAs(User::factory()->staff(SystemRole::SuperAdmin)->create())->get(route('admin.dashboard'))->assertOk()->assertInertia(function (Assert $p) {
        $props = $p->toArray()['props'];
        $attention = collect($props['attention'])->keyBy('label');
        $groups = collect($props['groups'])->keyBy('title')->map(fn ($g) => collect($g['items'])->pluck('value', 'label'));

        expect($attention['Applications to bid waiting for approval']['count'])->toBe(1)
            ->and($attention['Applications to bid waiting for approval']['urgent'])->toBeTrue()
            ->and($attention['Public listings with legal checks incomplete (deposits closed)']['count'])->toBe(1)
            ->and($groups['Auctions']['Live now'])->toBe(1)->and($groups['Auctions']['Closing within 24 hours'])->toBe(1)
            ->and($groups['Auctions']['Under offer'])->toBe(1)
            ->and($groups['Money']['Deposits held'])->toBe(5000000)
            ->and($props['health'])->not->toBeEmpty();
    });
});

it('shows each person only the figures their role allows', function () {
    dashListing('live-home', ListingStatus::Live);
    $this->actingAs(User::factory()->staff(SystemRole::KycOfficer)->create())->get(route('admin.dashboard'))->assertOk()->assertInertia(function (Assert $p) {
        $titles = collect($p->toArray()['props']['groups'])->pluck('title')->all();
        expect($titles)->not->toContain('Money')->not->toContain('Settlement');
    });
});
