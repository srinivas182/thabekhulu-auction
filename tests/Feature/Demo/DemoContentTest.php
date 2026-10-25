<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Demo\Support\DemoContent;
use App\Domains\Demo\Support\SampleImage;
use App\Domains\Events\Models\Event;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CmsSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]);
    User::factory()->staff(SystemRole::SuperAdmin)->create();
});

it('adds marked sample content once, and the public pages show it', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class); // second run adds nothing

    expect(Listing::where('slug', 'like', 'sample-%')->count())->toBe(7)
        ->and(Listing::where('slug', 'like', 'sample-%')->get()->every(fn ($l) => str_ends_with($l->title, '(sample)') && $l->getMedia('photos')->count() === 1))->toBeTrue()
        ->and(Event::where('slug', 'sample-investor-briefing')->count())->toBe(1)
        ->and(DB::table('posts')->where('slug', 'like', 'sample-%')->count())->toBe(2);

    $this->get('/')->assertOk();
    $this->get('/auctions')->assertOk()->assertSee('Four-bedroom family home with pool');
    $this->get('/auctions/homes/sandton/sample-family-home-sandton')->assertOk();
    $this->get('/auctions/mining/emalahleni/sample-coal-prospecting-right-emalahleni')->assertOk();
    $this->get('/events/sample-investor-briefing')->assertOk();
});

it('turns Pexels links into image links and nothing else', function () {
    expect(DemoContent::pexelsImage('https://www.pexels.com/photo/modern-house-with-pool-2497637/'))->toBe('https://images.pexels.com/photos/2497637/pexels-photo-2497637.jpeg?auto=compress&cs=tinysrgb&w=1600')
        ->and(DemoContent::pexelsImage('https://www.pexels.com/photo/2062736/'))->toContain('/photos/2062736/')
        ->and(DemoContent::pexelsImage('https://images.pexels.com/photos/13458422/pexels-photo-13458422.jpeg?w=500'))->toContain('/photos/13458422/')
        ->and(DemoContent::pexelsImage('https://www.gettyimages.com/detail/photo/123'))->toBeNull()
        ->and(DemoContent::pexelsImage('https://evil.example/photo/1/'))->toBeNull();
});

it('replaces sample photos from the list, keeping the old one if a download fails', function () {
    $this->seed(DemoSeeder::class);
    $jpeg = file_get_contents(SampleImage::make('Homes', 'Downloaded'));
    Http::fake([
        'images.pexels.com/photos/2497637/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        'images.pexels.com/photos/999/*' => Http::response('nope', 404),
    ]);
    $this->artisan('demo:photos --template')->assertSuccessful();
    file_put_contents(storage_path('app/demo-photos.txt'), implode("\n", [
        '# test', 'sample-family-home-sandton https://www.pexels.com/photo/modern-house-2497637/',
        'sample-farm-water-rights-tzaneen https://www.pexels.com/photo/farm-999/', 'sample-shopping-centre-rustenburg https://www.gettyimages.com/x/1',
    ]));
    $before = Listing::where('slug', 'sample-farm-water-rights-tzaneen')->first()->getFirstMedia('photos')->id;

    $this->artisan('demo:photos')->assertSuccessful();

    expect(Listing::where('slug', 'sample-family-home-sandton')->first()->getFirstMedia('photos')->file_name)->toBe('sample-family-home-sandton-0.jpg')
        ->and(Listing::where('slug', 'sample-farm-water-rights-tzaneen')->first()->getFirstMedia('photos')->id)->toBe($before);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'gettyimages'));
});

it('removes sample content but keeps a sample with real activity', function () {
    $this->seed(DemoSeeder::class);
    $l = Listing::where('slug', 'sample-family-home-sandton')->first();
    $buyer = User::factory()->create(['type' => 'buyer']);
    $ap = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $buyer->id, 'status' => 'approved', 'bidder_number' => '1234']);
    Deposit::create(['listing_id' => $l->id, 'user_id' => $buyer->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => 'TDG-D-1234-ABCDE', 'status' => 'awaiting']);

    $this->artisan('demo:remove --force')->assertSuccessful();

    expect(Listing::where('slug', 'like', 'sample-%')->pluck('slug')->all())->toBe(['sample-family-home-sandton'])
        ->and(Event::where('slug', 'like', 'sample-%')->count())->toBe(0)
        ->and(DB::table('posts')->where('slug', 'like', 'sample-%')->count())->toBe(0);
});
