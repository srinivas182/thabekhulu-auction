<?php

use App\Domains\Cms\Models\MenuItem;
use App\Domains\Cms\Models\Page;
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

it('never overwrites an edited page or touches the legal pages', function () {
    $about = Page::where('slug', 'about')->first();
    $about->update(['blocks' => [['type' => 'rich_text', 'html' => '<p>Our own words.</p>']], 'needs_content' => false]);
    $rules = Page::where('slug', 'rules-of-auction')->first()->blocks;

    $this->artisan('content:starter')->assertSuccessful();

    expect(Page::where('slug', 'about')->first()->blocks[0]['html'])->toBe('<p>Our own words.</p>')
        ->and(Page::where('slug', 'rules-of-auction')->first()->blocks)->toBe($rules);
});
