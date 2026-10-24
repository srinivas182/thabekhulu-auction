<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Events\Models\Event;
use App\Domains\Growth\Notifications\GrowthMail;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->seed([AccessSeeder::class, CatalogueSeeder::class]);
    $this->buyer = User::factory()->create(['type' => 'buyer']);
});

function growthListing(string $slug, ListingStatus $status = ListingStatus::Live, ?int $endsInHours = 240): Listing
{
    return Listing::create(['category_id' => Category::where('slug', 'homes')->value('id'), 'city_id' => City::where('slug', 'emalahleni')->value('id'), 'title' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug,
        'deal_type' => 'commission', 'seller_net_price' => 800000, 'fee_bps' => 2000, 'minimum_price' => 1000000, 'deposit_amount' => 50000, 'deposit_terms' => 'applied',
        'auction_type' => 'open', 'status' => $status, 'starts_at' => now()->subDay(), 'ends_at' => now()->addHours($endsInHours), 'summary' => 'Three-bedroom home.']);
}

it('renders listing structured data and absolute preview tags', function () {
    $l = growthListing('family-home');
    $r = $this->get("/auctions/homes/emalahleni/{$l->slug}");

    $r->assertOk()->assertSee('"@type":"RealEstateListing"', false);
    $r->assertInertia(fn (Assert $p) => $p->where('seo.type', 'product')->where('seo.canonical', fn ($u) => str_starts_with($u, 'http')));
});

it('describes events for search engines without exposing online links', function () {
    $e = Event::create(['title' => 'Investor webinar', 'slug' => 'investor-webinar', 'kind' => 'online', 'online_link' => 'https://meet.example.com/secret-room',
        'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2), 'price' => 0, 'capacity' => 100, 'status' => 'published']);
    Event::create(['title' => 'Draft day', 'slug' => 'draft-day', 'kind' => 'in_person', 'venue' => 'x', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(6), 'price' => 0, 'capacity' => 1, 'status' => 'draft']);

    $html = $this->get('/events/investor-webinar')->assertOk()->getContent();
    expect($html)->toContain('"@type":"Event"')->toContain('OnlineEventAttendanceMode')->not->toContain('secret-room');

    $sitemap = $this->get('/sitemap.xml')->getContent();
    expect($sitemap)->toContain('/events/investor-webinar')->not->toContain('/events/draft-day');

    $home = $this->get('/')->getContent();
    expect($home)->toContain('"@type":"Organization"')->not->toContain('SearchAction');
});

it('alerts saved searches about new listings only, once each', function () {
    growthListing('existing-home');
    $this->actingAs($this->buyer)->post(route('account.saved-searches.store'), ['name' => 'Homes in Emalahleni', 'filters' => ['category' => 'homes', 'junk' => 'x']])->assertSessionHasNoErrors();
    expect(json_decode((string) DB::table('saved_searches')->value('filters'), true))->toBe(['category' => 'homes']);

    $this->artisan('growth:alerts');
    Notification::assertNothingSent(); // the existing listing was recorded at save time

    growthListing('new-home');
    $this->artisan('growth:alerts');
    $this->artisan('growth:alerts');
    Notification::assertSentToTimes($this->buyer, GrowthMail::class, 1);
    Notification::assertSentTo($this->buyer, GrowthMail::class, fn ($n) => $n->kind === 'saved_search' && str_contains(implode(' ', $n->lines), 'New home'));
});

it('lets buyers watch listings and reminds watchers, not bidders, once', function () {
    $l = growthListing('closing-home', endsInHours: 20);
    BidEngine::state($l);
    $bidder = User::factory()->create(['type' => 'buyer']);
    $ap = BidderApproval::create(['listing_id' => $l->id, 'user_id' => $bidder->id, 'status' => 'approved', 'bidder_number' => '1234']);
    Deposit::create(['listing_id' => $l->id, 'user_id' => $bidder->id, 'bidder_approval_id' => $ap->id, 'amount' => 5000000, 'reference' => 'TDG-D-1234-AAAAA', 'status' => 'received']);

    foreach ([$this->buyer, $bidder] as $u) {
        $this->actingAs($u)->post(route('auctions.watch', $l->ulid))->assertRedirect();
    }
    $this->actingAs($this->buyer)->get("/auctions/homes/emalahleni/{$l->slug}")->assertInertia(fn (Assert $p) => $p->where('watching', true));

    $this->artisan('growth:alerts');
    $this->artisan('growth:alerts');
    Notification::assertSentToTimes($this->buyer, GrowthMail::class, 1);
    Notification::assertNotSentTo($bidder, GrowthMail::class);

    $this->actingAs($this->buyer)->post(route('auctions.watch', $l->ulid));
    expect(DB::table('watchlist_items')->where('user_id', $this->buyer->id)->exists())->toBeFalse();
});

it('keeps the first campaign source of a visitor who registers', function () {
    $this->get('/?utm_source=share&utm_medium=whatsapp&utm_campaign=listing')->assertOk();
    $this->get('/?utm_source=google')->assertOk(); // first touch wins

    $this->post('/register', ['first_name' => 'Zola', 'last_name' => 'Ndaba', 'email' => 'zola@example.com', 'mobile' => '0825550123', 'password' => 'Very-strong-pass-2026',
        'password_confirmation' => 'Very-strong-pass-2026', 'terms' => '1', 'popia' => '1'])->assertSessionHasNoErrors();

    expect(User::where('email', 'zola@example.com')->first()->attribution)->toMatchArray(['source' => 'share', 'medium' => 'whatsapp', 'campaign' => 'listing']);
});

it('runs a double opt-in newsletter without revealing who is subscribed', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'Reader@Example.com'])->assertSessionHasErrors('consent');
    $this->post(route('newsletter.subscribe'), ['email' => 'Reader@Example.com', 'consent' => '1'])->assertSessionHasNoErrors();
    $sub = DB::table('newsletter_subscribers')->first();
    expect($sub->email)->toBe('reader@example.com')->and($sub->status)->toBe('pending');
    Notification::assertSentOnDemand(GrowthMail::class, fn ($n) => $n->kind === 'newsletter_confirm');

    $this->get(route('newsletter.confirm', ['subscriber' => $sub->ulid]))->assertForbidden(); // unsigned
    $this->get(URL::temporarySignedRoute('newsletter.confirm', now()->addDay(), ['subscriber' => $sub->ulid]))->assertRedirect('/');
    expect(DB::table('newsletter_subscribers')->value('status'))->toBe('confirmed');

    Notification::fake();
    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com', 'consent' => '1'])->assertSessionHasNoErrors(); // same reply…
    Notification::assertNothingSent();                                                                                           // …but nothing changes

    $csv = $this->actingAs(User::factory()->staff(SystemRole::SuperAdmin)->create())->get(route('admin.newsletter.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('reader@example.com')->toContain('newsletter/unsubscribe');
    $this->actingAs(User::factory()->staff(SystemRole::Finance)->create())->get(route('admin.newsletter.export'))->assertForbidden();

    $this->get(URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $sub->ulid]))->assertRedirect('/');
    expect(DB::table('newsletter_subscribers')->value('status'))->toBe('unsubscribed');
});
