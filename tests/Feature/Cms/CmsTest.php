<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Cms\Models\Collection;
use App\Domains\Cms\Models\Page;
use App\Domains\Cms\Settings\HomeSettings;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CmsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]);
    $this->editor = User::factory()->staff(SystemRole::ContentEditor)->create();
});

function liveMine(string $commodity = 'Coal', array $overrides = []): Listing
{
    static $n = 0;
    $n++;
    $mining = Category::where('slug', 'mining')->firstOrFail();
    $listing = Listing::create(array_merge([
        'category_id' => $mining->id, 'city_id' => City::where('slug', 'emalahleni')->value('id'),
        'title' => "Mine {$n}", 'slug' => "mine-{$n}", 'deal_type' => 'commission', 'seller_net_price' => 1000000,
        'fee_bps' => 2000, 'minimum_price' => 1250000, 'deposit_amount' => 1200000, 'deposit_terms' => 'security',
        'auction_type' => 'open', 'status' => ListingStatus::Live, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(5), 'approved_at' => now(),
    ], $overrides));
    $listing->attributeValues()->create(['category_attribute_id' => $mining->attributeDefinitions()->where('key', 'commodity')->value('id'), 'value' => $commodity]);

    return $listing;
}

it('seeds the legal and information pages flagged as needing content', function () {
    expect(Page::where('is_system', true)->count())->toBe(10)
        ->and(Page::where('slug', 'paia-manual')->firstOrFail()->needs_content)->toBeTrue()
        ->and(Page::where('slug', 'automatically-available-records')->exists())->toBeTrue();

    $this->get('/disclaimer')->assertOk()->assertInertia(fn (Assert $p) => $p->component('public/page')->where('page.title', 'Disclaimer'));
});

it('shows only published pages and does not shadow application routes', function () {
    Page::where('slug', 'fees')->update(['status' => 'draft']);

    $this->get('/fees')->assertNotFound();
    $this->get('/no-such-page')->assertNotFound();
    $this->get('/auctions')->assertOk()->assertInertia(fn (Assert $p) => $p->component('public/auctions/index'));
    $this->get('/login')->assertOk();
});

it('cleans block HTML, rejects unknown blocks and unsafe links, and publishes FAQ structured data', function () {
    $page = Page::where('slug', 'buyers-guide')->firstOrFail();
    $blocks = [
        ['type' => 'rich_text', 'html' => '<p onclick="x()">Hello<script>alert(1)</script></p>'],
        ['type' => 'faq', 'items' => [['question' => 'Is my deposit refunded?', 'answer' => '<p>Yes, in full.</p>']]],
        ['type' => 'cta', 'title' => 'Ready?', 'text' => '', 'button_label' => 'Browse', 'button_url' => '/auctions'],
    ];

    $this->actingAs($this->editor)->put(route('admin.cms.pages.update', $page), [
        'title' => $page->title, 'slug' => $page->slug, 'status' => 'published', 'blocks' => $blocks,
    ])->assertSessionHasNoErrors();

    $stored = json_encode($page->fresh()->blocks);
    expect($stored)->not->toContain('script')->not->toContain('onclick');

    $html = $this->get('/buyers-guide')->getContent();
    expect($html)->toContain('FAQPage')->toContain('Is my deposit refunded?');

    $this->actingAs($this->editor)->put(route('admin.cms.pages.update', $page), [
        'title' => 'x', 'slug' => $page->slug, 'status' => 'published', 'blocks' => [['type' => 'iframe', 'src' => 'x']],
    ])->assertSessionHasErrors('blocks.0');

    $this->actingAs($this->editor)->put(route('admin.cms.pages.update', $page), [
        'title' => 'x', 'slug' => $page->slug, 'status' => 'published',
        'blocks' => [['type' => 'cta', 'title' => 'x', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)']],
    ])->assertSessionHasErrors('blocks.0.button_url');
});

it('blocks reserved addresses, keeps standard page addresses and protects them from deletion', function () {
    $this->actingAs($this->editor)->post(route('admin.cms.pages.store'), ['title' => 'Selling a mining right']);
    $page = Page::where('slug', 'selling-a-mining-right')->firstOrFail();

    $this->actingAs($this->editor)->put(route('admin.cms.pages.update', $page), [
        'title' => 'x', 'slug' => 'admin', 'status' => 'draft', 'blocks' => [],
    ])->assertSessionHasErrors('slug');

    $privacy = Page::where('slug', 'privacy')->firstOrFail();
    $this->actingAs($this->editor)->put(route('admin.cms.pages.update', $privacy), [
        'title' => 'Privacy', 'slug' => 'something-else', 'status' => 'published', 'blocks' => [],
    ]);
    expect($privacy->fresh()->slug)->toBe('privacy');

    $this->actingAs($this->editor)->delete(route('admin.cms.pages.destroy', $privacy))->assertForbidden();
    $this->actingAs($this->editor)->delete(route('admin.cms.pages.destroy', $page))->assertRedirect();
});

it('updates menus everywhere and rejects unsafe links', function () {
    $this->actingAs($this->editor)->put(route('admin.cms.menus.update', 'header'), [
        'items' => [['label' => 'Mining', 'url' => '/auctions/mining', 'newTab' => false], ['label' => 'Fees', 'url' => '/fees']],
    ])->assertSessionHasNoErrors();

    $this->get('/')->assertInertia(fn (Assert $p) => $p->where('site.header.0.label', 'Mining')->has('site.header', 2));

    $this->actingAs($this->editor)->put(route('admin.cms.menus.update', 'footer'), [
        'items' => [['label' => 'Bad', 'url' => 'javascript:alert(1)']],
    ])->assertSessionHasErrors('items.0.url');
});

it('builds the home page from real listings, hiding empty sections', function () {
    $this->get('/')->assertInertia(fn (Assert $p) => $p
        ->component('public/home')
        ->where('featured', null)
        ->has('closingSoon', 0)
        ->has('collections', 0)
        ->where('hero.title', 'Bid on South African property, from anywhere.'));

    $coal = liveMine('Coal');
    liveMine('Gold');
    $home = app(HomeSettings::class);
    $home->featured_listing = $coal->ulid;
    $home->save();

    $this->get('/')->assertInertia(fn (Assert $p) => $p
        ->where('featured.title', $coal->title)
        ->has('closingSoon', 2)
        ->where('collections', fn ($c) => collect($c)->firstWhere('name', 'Coal mining')['count'] === 1)
        ->where('categories', fn ($c) => collect($c)->firstWhere('slug', 'mining')['count'] === 2)
        ->has('calendar', 1)
        ->missing('featured.sellerNetPrice'));
});

it('saves collections and home settings, with the FFC number in the footer', function () {
    $mining = Category::where('slug', 'mining')->firstOrFail();
    $commodity = $mining->attributeDefinitions()->where('key', 'commodity')->firstOrFail();

    $this->actingAs($this->editor)->post(route('admin.cms.collections.store'), [
        'name' => 'Gold mining', 'category_id' => $mining->id, 'category_attribute_id' => $commodity->id, 'attribute_value' => 'Gold', 'is_visible' => true,
    ])->assertSessionHasNoErrors();
    liveMine('Gold');
    expect(Collection::where('slug', 'gold-mining')->firstOrFail()->listings()->count())->toBe(1);

    $this->actingAs($this->editor)->put(route('admin.cms.home.update'), [
        'hero_title' => 'Invest in South African mining', 'hero_text' => 'Verified mines, open bidding.',
        'steps' => [['title' => 'Register', 'text' => 'Verify once.'], ['title' => 'Bid', 'text' => 'Bid online.']],
        'ffc_number' => '202612345', 'show_calendar' => false,
    ])->assertSessionHasNoErrors();

    $this->get('/')->assertInertia(fn (Assert $p) => $p->where('hero.title', 'Invest in South African mining')->where('site.ffc', '202612345')->has('calendar', 0));
});

it('is limited to staff with cms.manage', function () {
    $finance = User::factory()->staff(SystemRole::Finance)->create();

    $this->actingAs($finance)->get(route('admin.cms.pages'))->assertForbidden();
    $this->actingAs($this->editor)->get(route('admin.cms.pages'))->assertOk();
    $this->actingAs($this->editor)->get(route('admin.cms.menus'))->assertOk();
    $this->actingAs($this->editor)->get(route('admin.cms.collections'))->assertOk();
    $this->actingAs($this->editor)->get(route('admin.cms.home'))->assertOk();
});
