<?php

use App\Domains\Platform\Console\SecurityCheck;

it('passes only with production-safe settings', function () {
    config(['app.env' => 'production', 'app.debug' => false, 'app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.url' => 'https://thabekhuludevelopment.org.za',
        'session.secure' => true, 'session.http_only' => true, 'session.encrypt' => true, 'mail.default' => 'smtp', 'queue.default' => 'redis', 'broadcasting.default' => 'reverb']);
    expect(SecurityCheck::problems())->toBe([]);
    $this->artisan('security:check')->assertSuccessful();

    config(['app.debug' => true, 'app.key' => (string) config('app.key')]);
    config(['app.key' => SecurityCheck::TEST_KEY_PREFIX.'xxxx']);
    expect(array_keys(SecurityCheck::problems()))->toContain('APP_DEBUG must be false')->toContain('APP_KEY must be a unique production key');
    $this->artisan('security:check')->assertFailed();
});

it('defaults session cookies to HTTPS-only in production', function () {
    $config = require base_path('config/session.php');
    expect(str_contains(file_get_contents(base_path('config/session.php')), "env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')"))->toBeTrue()
        ->and($config['http_only'])->toBeTrue();
});
