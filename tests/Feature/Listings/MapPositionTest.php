<?php

use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
});

function mapListing(string $slug, ?float $lat = null, ?float $lng = null): Listing
{
    return Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'durban')->value('id'), 'title' => ucfirst($slug), 'slug' => $slug,
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3), 'latitude' => $lat, 'longitude' => $lng]);
}

it('gives every seeded city map coordinates', function () {
    expect(City::whereNull('latitude')->count())->toBe(0)
        ->and((float) City::where('slug', 'durban')->value('latitude'))->toBe(-29.86);
});

it('puts listings without coordinates at their city, marked approximate; exact ones stay exact', function () {
    mapListing('no-coordinates');
    mapListing('exact-home', -29.851234, 31.021234);

    $this->get('/auctions')->assertOk()->assertInertia(function (Assert $p) {
        $results = $p->toArray()['props']['results'];
        $cards = collect($results['data'] ?? $results)->keyBy('title');
        $approx = $cards['No-coordinates']['position'];
        expect($approx['approximate'])->toBeTrue()
            ->and(abs($approx['lat'] - -29.86))->toBeLessThan(0.05)->and(abs($approx['lng'] - 31.02))->toBeLessThan(0.05)
            ->and($cards['Exact-home']['position'])->toBe(['lat' => -29.85, 'lng' => 31.02, 'approximate' => false]);
    });
});
