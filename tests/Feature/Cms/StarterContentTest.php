<?php

use App\Domains\Cms\Models\MenuItem;
use App\Domains\Cms\Models\Page;
use App\Domains\Settings\Settings\GeneralSettings;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CmsSeeder;

beforeEach(fn () => $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]));

it('fills About and How it works, keeps them marked for review, and adds Home first', function () {
    $this->get('/')->assertOk(); // the header menu is now cached, as on a live site
    $this->artisan('content:starter')->assertSuccessful();
    $this->artisan('content:starter')->assertSuccessful(); // safe to run again

    $about = $this->get('/about')->assertOk()->getContent();
    expect($about)->toContain('Property and mining auctions, run openly online')->toContain('Why buyers trust the process')->toContain('Register to bid')
        ->not->toContain('Text to be supplied by Thabekhulu');

    $how = $this->get('/how-it-works')->assertOk()->getContent();
    expect($how)->toContain('From registration to transfer')->toContain('Pay the deposit')->toContain('Are bids binding?')->toContain('section 11');

    expect(Page::where('slug', 'about')->value('needs_content'))->toBeTrue()
        ->and(MenuItem::where('menu', 'header')->where('url', '/')->count())->toBe(1)
        ->and(MenuItem::where('menu', 'header')->orderBy('sort_order')->value('label'))->toBe('Home');

    // What visitors actually see: the cached header includes Home, first.
    $this->get('/')->assertInertia(fn ($p) => $p->where('site.header.0.label', 'Home'));
});

it('never overwrites an edited page, including a legal page the attorney has written', function () {
    $about = Page::where('slug', 'about')->first();
    $about->update(['blocks' => [['type' => 'rich_text', 'html' => '<p>Our own words.</p>']], 'needs_content' => false]);
    Page::where('slug', 'rules-of-auction')->update(['blocks' => [['type' => 'rich_text', 'html' => '<p>Attorney’s rules.</p>']], 'needs_content' => false]);

    $this->artisan('content:starter')->assertSuccessful();

    expect(Page::where('slug', 'about')->first()->blocks[0]['html'])->toBe('<p>Our own words.</p>')
        ->and(Page::where('slug', 'rules-of-auction')->first()->blocks[0]['html'])->toBe('<p>Attorney’s rules.</p>');
});

it('fills the buyer’s guide, fees and legal pages, marking legal drafts for the attorney', function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]);
    $this->artisan('content:starter')->assertSuccessful();

    foreach (['buyers-guide', 'rules-of-auction', 'fees', 'privacy', 'paia-manual', 'disclaimer'] as $slug) {
        $page = Page::where('slug', $slug)->firstOrFail();
        $html = json_encode($page->blocks);
        expect($page->blocks)->not->toBeEmpty()->and($page->needs_content)->toBeTrue();
        expect(str_contains($html, 'Draft for review'))->toBe(in_array($slug, ['rules-of-auction', 'privacy', 'paia-manual', 'disclaimer'], true));
        $this->get("/{$slug}")->assertOk();
    }
    expect(json_encode(Page::where('slug', 'rules-of-auction')->value('blocks')))->toContain('Consumer Protection Act');

    // No [placeholders] left anywhere, and the company details are in place.
    foreach (['about', 'how-it-works', 'buyers-guide', 'rules-of-auction', 'fees', 'privacy', 'paia-manual', 'disclaimer', 'contact'] as $slug) {
        expect(json_encode(Page::where('slug', $slug)->value('blocks'), JSON_UNESCAPED_UNICODE))->not->toMatch('/\\[[A-Za-z]/');
    }
    expect(html_entity_decode(json_encode(Page::where('slug', 'contact')->value('blocks'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))->toContain('+27 61 961 1898')->toContain('admin@stevemaqueens.co.za');
    expect(json_encode(Page::where('slug', 'paia-manual')->value('blocks')))->toContain('Nonstikelelo');
    expect(app(GeneralSettings::class)->contact_email)->toBe('admin@stevemaqueens.co.za');

    // Pages someone has edited are never overwritten.
    Page::where('slug', 'fees')->update(['blocks' => [], 'needs_content' => false]);
    $this->artisan('content:starter')->assertSuccessful();
    expect(Page::where('slug', 'fees')->first()->blocks)->toBe([]);
});
