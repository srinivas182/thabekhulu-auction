<?php

use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Demo\Support\SampleImage;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Storage;

test('the registration page shows a real listing photo when there is one, and works without', function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->get(route('register'))->assertOk()->assertInertia(fn ($p) => $p->component('auth/register')->where('cover', null));

    Storage::fake('public');
    $listing = Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'),
        'city_id' => City::where('slug', 'sandton')->value('id'), 'title' => 'Family home', 'slug' => 'family-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
    $listing->addMedia(SampleImage::make('Homes', 'Family home'))->toMediaCollection('photos');

    $this->get(route('register'))->assertInertia(fn ($p) => $p->where('cover', fn ($url) => is_string($url) && str_contains($url, '/storage/')));
});
