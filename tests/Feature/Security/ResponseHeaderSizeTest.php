<?php

use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CmsSeeder;

/*
 * Apache refuses a response header line over 8 KB with an empty 500 (no Laravel log).
 * The preload Link header grew past that on /login and /register; it's now capped.
 */
it('keeps every page’s response headers well under Apache’s 8 KB limit', function () {
    $this->seed([AccessSeeder::class, CatalogueSeeder::class, CmsSeeder::class]);
    config(['app.url' => 'https://thabekhuludevelopment.org.za']);

    foreach (['/', '/login', '/register', '/forgot-password', '/auctions', '/how-it-works', '/about'] as $path) {
        $response = $this->get($path)->assertOk();
        $link = (string) $response->headers->get('Link', '');
        expect(strlen($link))->toBeLessThan(4096, "{$path} sends a {$link} Link header of ".strlen($link).' bytes');
    }
});
