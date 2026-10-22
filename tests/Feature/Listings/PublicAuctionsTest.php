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

function publicListing(array $overrides = [], string $category = 'mining', string $city = 'emalahleni'): Listing
{
    static $n = 0;
    $n++;

    return Listing::create(array_merge([
        'category_id' => Category::where('slug', $category)->value('id'),
        'city_id' => City::where('slug', $city)->value('id'),
        'title' => "Coal prospecting right {$n}",
        'slug' => "coal-prospecting-right-{$n}",
        'summary' => 'A 640 ha coal prospecting right near Emalahleni.',
        'description' => '<p>Rail siding nearby.</p>',
        'deal_type' => 'commission',
        'seller_net_price' => 1777777,
        'renovation_cost' => 0,
        'fee_bps' => 2000,
        'minimum_price' => 2300000,
        'deposit_amount' => 1200000,
        'deposit_terms' => 'security',
        'auction_type' => 'open',
        'address' => '12 Secret Street',
        'review_note' => 'Internal review comment',
        'latitude' => -25.871234,
        'longitude' => 29.233456,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
        'status' => ListingStatus::Live,
        'approved_at' => now(),
    ], $overrides));
}

it('shows live and coming-soon listings and hides everything else', function (ListingStatus $status, int $code) {
    $listing = publicListing(['status' => $status]);

    $this->get("/auctions/mining/emalahleni/{$listing->slug}")->assertStatus($code);
})->with([
    'live' => [ListingStatus::Live, 200],
    'coming soon' => [ListingStatus::Scheduled, 200],
    'draft' => [ListingStatus::Draft, 404],
    'in review' => [ListingStatus::InReview, 404],
    'changes requested' => [ListingStatus::ChangesRequested, 404],
    'withdrawn' => [ListingStatus::Withdrawn, 404],
]);

it('never exposes seller terms, the fee, the street address or review notes', function () {
    $listing = publicListing();
    $listing->category->documentTypes()->where('visibility', 'internal')->update(['name' => 'Secret internal document']);

    $page = $this->get("/auctions/mining/emalahleni/{$listing->slug}");
    $html = $page->getContent();

    foreach (['1777777', '1 777 777', '12 Secret Street', 'Internal review comment', 'seller_net_price', 'fee_bps', 'Secret internal document', '25.871234'] as $secret) {
        expect($html)->not->toContain($secret);
    }

    $page->assertInertia(fn (Assert $p) => $p
        ->component('public/auctions/show')
        ->missing('listing.sellerNetPrice')
        ->missing('listing.address')
        ->missing('listing.feeBps')
        ->where('listing.position', ['lat' => -25.87, 'lng' => 29.23]));

    $index = $this->get('/auctions')->getContent();
    expect($index)->not->toContain('1777777')->not->toContain('12 Secret Street');
});

it('shows the verification notice and data-room document names on mining listings', function () {
    $listing = publicListing();

    $this->get("/auctions/mining/emalahleni/{$listing->slug}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('listing.notice', fn ($n) => str_contains($n, 'does not by itself transfer a mining right'))
            ->where('listing.documents', fn ($docs) => collect($docs)->pluck('name')->contains('Mining or prospecting right')
                && collect($docs)->pluck('name')->doesntContain("Seller's authority to sell"))
            ->where('listing.depositTerms', 'security'));
});

it('publishes structured data for search engines', function () {
    $listing = publicListing();

    $html = $this->get("/auctions/mining/emalahleni/{$listing->slug}")->getContent();

    expect($html)->toContain('application/ld+json')
        ->toContain('RealEstateListing')
        ->toContain('"priceCurrency":"ZAR"')
        ->not->toContain('1777777')
        ->toContain('BreadcrumbList');
});

it('redirects a wrong city in the address to the canonical listing URL', function () {
    $listing = publicListing();

    $this->get("/auctions/mining/pretoria/{$listing->slug}")
        ->assertRedirect("/auctions/mining/emalahleni/{$listing->slug}")
        ->assertStatus(301);
});

it('only embeds privacy-enhanced YouTube or Vimeo players', function () {
    $listing = publicListing(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

    $response = $this->get("/auctions/mining/emalahleni/{$listing->slug}")
        ->assertInertia(fn (Assert $p) => $p->where('listing.videoEmbed', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'));

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('frame-src https://www.youtube-nocookie.com https://player.vimeo.com')
        ->toContain('https://tile.openstreetmap.org');
});

it('filters by category, province, price, status and renovation', function () {
    publicListing(['title' => 'Mine A', 'minimum_price' => 2000000]);
    publicListing(['title' => 'Mine B', 'minimum_price' => 9000000, 'status' => ListingStatus::Scheduled, 'starts_at' => now()->addDays(2)]);
    publicListing(['title' => 'Farm C', 'minimum_price' => 500000, 'renovation_cost' => 100000, 'deal_type' => 'renovated', 'seller_price' => 300000], 'land', 'polokwane');

    $titles = fn ($query) => collect($this->get('/auctions?'.http_build_query($query))->original->getData()['page']['props']['results']['data'])->pluck('title')->sort()->values()->all();

    expect($titles([]))->toBe(['Farm C', 'Mine A', 'Mine B'])
        ->and($titles(['category' => 'mining']))->toBe(['Mine A', 'Mine B'])
        ->and($titles(['province' => 'limpopo']))->toBe(['Farm C'])
        ->and($titles(['min' => 1000000, 'max' => 5000000]))->toBe(['Mine A'])
        ->and($titles(['status' => 'soon']))->toBe(['Mine B'])
        ->and($titles(['renovated' => 1]))->toBe(['Farm C']);

    $this->get('/auctions/mining')->assertInertia(fn (Assert $p) => $p->where('heading', 'Mining auctions')->has('results.data', 2));
    $this->get('/auctions/mining/emalahleni')->assertInertia(fn (Assert $p) => $p->where('heading', 'Mining auctions in Emalahleni'));
});

it('hides listings in hidden categories', function () {
    $listing = publicListing();
    Category::where('slug', 'mining')->update(['is_visible' => false]);

    $this->get('/auctions/mining')->assertNotFound();
    $this->get('/auctions')->assertInertia(fn (Assert $p) => $p->has('results.data', 0));
    expect($listing->exists)->toBeTrue();
});

it('lists public listings in the sitemap', function () {
    $live = publicListing();
    $draft = publicListing(['status' => ListingStatus::Draft]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee("/auctions/mining/emalahleni/{$live->slug}", escape: false)
        ->assertDontSee($draft->slug, escape: false);
});
