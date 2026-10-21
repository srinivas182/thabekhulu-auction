<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Support\PermissionCatalogue;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\SuperAdminSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

it('seeds every permission and system role, and can run again safely', function () {
    $this->seed(AccessSeeder::class);

    expect(Permission::count())->toBe(count(PermissionCatalogue::all()))
        ->and(Role::where('is_system', true)->count())->toBe(count(SystemRole::cases()))
        ->and(Role::findByName('super-admin')->permissions()->count())->toBe(count(PermissionCatalogue::all()))
        ->and(Role::findByName('support')->hasPermissionTo('staff.view'))->toBeFalse()
        ->and(Role::findByName('buyer')->permissions()->count())->toBe(0);
});

it('creates the first super admin once', function () {
    config(['platform.super_admin_email' => 'owner@example.com']);

    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', 'owner@example.com')->count())->toBe(0); // a super admin already existed

    User::query()->delete();
    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    expect(User::role('super-admin')->count())->toBe(1)
        ->and(User::firstOrFail()->email)->toBe('owner@example.com');
});

it('creates a custom role with chosen permissions', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.store'), [
        'label' => 'Regional manager',
        'description' => 'Oversees KwaZulu-Natal listings',
        'permissions' => ['listings.view', 'listings.publish', 'reports.view'],
    ])->assertRedirect(route('admin.roles.index'));

    $role = Role::where('name', 'regional-manager')->firstOrFail();

    expect((bool) $role->getAttribute('is_system'))->toBeFalse()
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['listings.publish', 'listings.view', 'reports.view']);
});

it('rejects permissions that are not in the catalogue', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.store'), [
        'label' => 'Bad role',
        'permissions' => ['everything.allowed'],
    ])->assertSessionHasErrors('permissions.0');
});

it('requires a unique role name', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.store'), ['label' => 'Finance'])
        ->assertSessionHasErrors('label');
});

it('does not allow system roles to be changed or deleted', function () {
    $finance = Role::findByName('finance');

    $this->actingAs($this->admin)->put(route('admin.roles.update', $finance), ['label' => 'Money'])->assertForbidden();
    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $finance))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.roles.edit', $finance))->assertOk();
});

it('blocks deleting a custom role that still has staff', function () {
    $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);
    User::factory()->staff('temp')->create();

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');
    expect(Role::where('name', 'temp')->exists())->toBeTrue();
});

it('gives a custom role’s staff exactly its permissions', function () {
    $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(['dashboard.view', 'roles.view']);
    $viewer = User::factory()->staff('viewer')->create();

    $this->actingAs($viewer)->get(route('admin.roles.index'))->assertOk();
    $this->actingAs($viewer)->get(route('admin.roles.create'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.staff.index'))->assertForbidden();
});
