<?php

use App\Providers\AppServiceProvider;

it('refuses to boot in production with debug enabled', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => true]);

    (new AppServiceProvider(app()))->boot();
})->throws(RuntimeException::class, 'APP_DEBUG must be false in production.');

it('boots in production with debug disabled', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);

    (new AppServiceProvider(app()))->boot();

    expect(true)->toBeTrue();
});
