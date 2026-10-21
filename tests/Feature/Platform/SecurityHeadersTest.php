<?php

it('sends a nonce-based content security policy', function () {
    $response = $this->get(route('home'));

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/");
});

it('uses the same nonce in the policy and on inline scripts', function () {
    $response = $this->get(route('home'));

    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

    expect($matches[1] ?? null)->not->toBeNull();
    $response->assertSee('nonce="'.$matches[1].'"', escape: false);
});

it('sends baseline security headers', function () {
    $this->get(route('home'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
});

it('only sends HSTS over HTTPS', function () {
    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('blocks search engines while indexing is off', function () {
    config(['platform.indexing' => false]);

    $this->get(route('home'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);
});

it('allows search engines once indexing is on', function () {
    config(['platform.indexing' => true]);

    $this->get(route('home'))
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertDontSee('<meta name="robots" content="noindex, nofollow">', escape: false);
});

it('can send the policy in report-only mode', function () {
    config(['platform.csp.report_only' => true]);

    $this->get(route('home'))
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only');
});
