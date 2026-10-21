<?php

use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the public home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/home'));
});

it('sends guests to the login page from the back office', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('serves the back office dashboard under /admin', function () {
    expect(route('admin.dashboard', absolute: false))->toBe('/admin');
});

it('shows system status on the back office dashboard', function () {
    $this->seed(AccessSeeder::class);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('health.database.ok', true)
            ->where('health.cache.ok', true)
            ->where('health.queue.ok', true));
});

it('shows a branded error page outside local development', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);

    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404));
});

it('keeps the framework error page in local development', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertDontSee('"component":"error"', escape: false);
});
