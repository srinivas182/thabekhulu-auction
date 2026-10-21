<?php

use App\Domains\Access\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(AccessSeeder::class));

it('sends staff to the back office after sign-in', function () {
    $staff = User::factory()->staff(SystemRole::Support)->create();

    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
});

it('requires staff to set up two-factor before using the back office', function () {
    $staff = User::factory()->staff(SystemRole::SuperAdmin)->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($staff)->get(route('admin.dashboard'))->assertRedirect(route('security.edit'));
    $this->actingAs($staff)->get(route('admin.staff.index'))->assertRedirect(route('security.edit'));
});

it('lets each role open only the pages it has permission for', function (SystemRole $role, string $route, int $status) {
    $staff = User::factory()->staff($role)->create();

    $this->actingAs($staff)->get(route($route))->assertStatus($status);
})->with([
    'super admin: staff' => [SystemRole::SuperAdmin, 'admin.staff.index', 200],
    'super admin: roles' => [SystemRole::SuperAdmin, 'admin.roles.index', 200],
    'support: dashboard' => [SystemRole::Support, 'admin.dashboard', 200],
    'support: staff' => [SystemRole::Support, 'admin.staff.index', 403],
    'support: roles' => [SystemRole::Support, 'admin.roles.index', 403],
    'finance: roles' => [SystemRole::Finance, 'admin.roles.index', 403],
    'auctioneer: create role' => [SystemRole::Auctioneer, 'admin.roles.create', 403],
]);

it('shares only the permissions a role has, for navigation', function () {
    $staff = User::factory()->staff(SystemRole::Support)->create();

    $this->actingAs($staff)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('enquiries.view')
                && ! collect($permissions)->contains('staff.view')));
});

it('shares no permissions with buyers', function () {
    $this->actingAs(User::factory()->create())->get(route('account'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', []));
});

it('signs out a deactivated account on its next request', function () {
    $staff = User::factory()->staff(SystemRole::Support)->create();

    $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();

    $staff->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('blocks deactivated accounts from signing in', function () {
    $staff = User::factory()->staff(SystemRole::Support)->deactivated()->create();

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('records the last sign-in time', function () {
    $buyer = User::factory()->create();

    $this->post(route('login.store'), ['email' => $buyer->email, 'password' => 'password']);

    expect($buyer->refresh()->last_login_at)->not->toBeNull();
});

it('never exposes internal ids or secrets to the browser', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.id')
            ->missing('auth.user.password')
            ->missing('auth.user.two_factor_secret')
            ->has('auth.user.ulid'));
});
