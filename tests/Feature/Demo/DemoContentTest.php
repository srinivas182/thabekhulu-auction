<?php

use App\Domains\Cms\Models\Post;
use App\Domains\Events\Models\Event;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CmsSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]);
});

it('adds clearly marked sample content once, and every page opens', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class); // safe to run again

    expect(Listing::count())->toBe(8)->and(Event::count())->toBe(1)->and(Post::count())->toBe(2)
        ->and(Listing::where('title', 'not like', 'Sample:%')->count())->toBe(0)
        ->and(DB::table('media')->where('collection_name', 'photos')->count())->toBe(8);

    $this->get('/')->assertOk();
    $this->get('/auctions')->assertOk()->assertInertia(fn (Assert $p) => $p->where('results.total', fn ($n) => $n >= 5));
    $this->get('/events/sample-investor-briefing')->assertOk();
    foreach (Listing::with(['category', 'city'])->get() as $l) {
        $this->get(PublicListing::url($l))->assertOk();
    }

    $sold = Listing::where('slug', 'sample-smallholding-brits')->firstOrFail();
    $this->get(PublicListing::url($sold->load(['category', 'city'])))->assertInertia(fn (Assert $p) => $p->where('auction.outcome', 'sold'));
    expect(User::where('email', 'sample.bidder@thabekhulu.invalid')->value('deactivated_at'))->not->toBeNull(); // the sample bidder can't sign in
});

it('removes all sample content, photos included', function () {
    $this->seed(DemoSeeder::class);
    $this->artisan('demo:remove --force')->assertSuccessful();

    expect(Listing::count())->toBe(0)->and(Event::count())->toBe(0)->and(Post::count())->toBe(0)
        ->and(DB::table('media')->count())->toBe(0)->and(DB::table('auction_results')->count())->toBe(0)
        ->and(User::where('email', 'sample.bidder@thabekhulu.invalid')->exists())->toBeFalse();
});
