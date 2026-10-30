<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Cms\Models\Page;
use App\Domains\Cms\Settings\HomeSettings;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Platform\Console\LaunchReset;
use App\Domains\Platform\Support\AuthCover;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CmsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]);
    $this->staff = User::factory()->staff(SystemRole::Finance)->create(['email' => 'finance@example.com']);
    $this->buyer = User::factory()->create(['type' => 'buyer']);
    $this->listing = Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'durban')->value('id'), 'title' => 'Test home', 'slug' => 'test-home',
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(3)]);
    $this->listing->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photos');
    $this->page = Page::where('slug', 'about')->firstOrFail();
    $this->page->addMedia(UploadedFile::fake()->image('team.jpg'))->toMediaCollection(collect($this->page->getRegisteredMediaCollections())->first()->name ?? 'default');
    $a = BidderApproval::create(['listing_id' => $this->listing->id, 'user_id' => $this->buyer->id, 'status' => 'approved', 'bidder_number' => '1234']);
    Deposit::create(['listing_id' => $this->listing->id, 'user_id' => $this->buyer->id, 'bidder_approval_id' => $a->id, 'amount' => 5000000, 'reference' => 'TDG-D-1234-ABCDE', 'status' => 'received']);
    Storage::disk('local')->put('verifications/x/id.enc', 'secret');
});

it('classifies every table as kept, partly kept or deleted', function () {
    expect(LaunchReset::unclassified())->toBe([]);
});

it('shows a dry run without changing anything', function () {
    $this->artisan('platform:launch-reset')->expectsOutputToContain('Dry run: nothing was changed')->assertSuccessful();
    expect(Listing::count())->toBe(1)->and(User::where('type', 'buyer')->count())->toBe(1)->and(Storage::disk('local')->exists('verifications/x/id.enc'))->toBeTrue();
});

it('deletes the test data, keeps staff and configuration, and runs only once', function () {
    $this->artisan('platform:launch-reset', ['--confirm' => LaunchReset::PHRASE, '--no-backup' => true])->assertSuccessful();

    expect(Listing::count())->toBe(0)
        ->and(User::where('type', 'buyer')->count())->toBe(0)
        ->and(Deposit::count())->toBe(0)
        ->and(DB::table('media')->where('model_type', Listing::class)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        // kept
        ->and(User::where('email', 'finance@example.com')->first()?->hasRole('finance'))->toBeTrue()
        ->and(Page::where('slug', 'about')->exists())->toBeTrue()
        ->and(DB::table('media')->where('model_type', Page::class)->count())->toBe(1)       // website page images stay
        ->and(Category::count())->toBeGreaterThan(0)->and(City::count())->toBeGreaterThan(0)
        ->and(DB::table('settings')->count())->toBeGreaterThan(0)
        ->and(DB::table('platform_launches')->count())->toBe(1)
        ->and(DB::table('activity_log')->where('event', 'launch_reset')->count())->toBe(1);

    // The home banner and sign-in photos were copied from the listing before it went, so those pages keep a photo.
    $home = app(HomeSettings::class)->refresh();
    expect($home->hero_image)->not->toBeNull()->and($home->auth_image)->not->toBeNull()
        ->and(Storage::disk('public')->exists((string) $home->hero_image))->toBeTrue()
        ->and(Storage::disk('public')->exists((string) $home->auth_image))->toBeTrue()
        ->and($home->featured_listing)->toBeNull()
        ->and(AuthCover::url())->not->toBeNull();

    $this->artisan('platform:launch-reset', ['--confirm' => LaunchReset::PHRASE, '--no-backup' => true])->expectsOutputToContain('already ran')->assertFailed();
});

it('deletes nothing if the backup fails', function () {
    // Point the backup at a database that doesn't exist (the open connection keeps working), so the dump fails on SQLite or MySQL.
    config(['database.connections.'.config('database.default').'.database' => '/nonexistent/no_such_database']);
    $this->artisan('platform:launch-reset', ['--confirm' => LaunchReset::PHRASE])->expectsOutputToContain('Backup failed')->assertFailed();
    expect(Listing::count())->toBe(1)->and(User::where('type', 'buyer')->count())->toBe(1)
        ->and(glob(storage_path('launch-backup-*')))->toBe([]); // no half-made backup left behind
});

it('lets staff upload and remove the site photos, which the home page uses', function () {
    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
    $this->actingAs($admin)->post(route('admin.cms.home.images.store', 'hero'), ['image' => UploadedFile::fake()->image('banner.jpg', 1600, 900)])->assertSessionHasNoErrors();
    $path = (string) app(HomeSettings::class)->refresh()->hero_image;
    expect(Storage::disk('public')->exists($path))->toBeTrue();
    $this->get('/')->assertInertia(fn ($p) => $p->where('hero.image', fn ($v) => is_string($v) && str_contains($v, 'brand/hero-')));

    $this->actingAs($admin)->post(route('admin.cms.home.images.store', 'auth'), ['image' => UploadedFile::fake()->image('small.jpg', 300, 200)])->assertSessionHasErrors('image');
    $this->actingAs($admin)->delete(route('admin.cms.home.images.destroy', 'hero'))->assertSessionHasNoErrors();
    expect(Storage::disk('public')->exists($path))->toBeFalse()->and(app(HomeSettings::class)->refresh()->hero_image)->toBeNull();
});
