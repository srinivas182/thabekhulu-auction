<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Platform\Console\SecurityCheck;
use App\Domains\Settings\Settings\SecuritySettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;

beforeEach(fn () => $this->seed(AccessSeeder::class));

it('sends staff without two-factor to set it up while the setting is on (the default)', function () {
    $staff = User::factory()->staff(SystemRole::SuperAdmin)->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    expect(app(SecuritySettings::class)->staff_two_factor_required)->toBeTrue();

    $this->actingAs($staff)->get('/admin/listings')->assertRedirect(route('security.edit'));
});

it('lets staff in with a password only when the setting is switched off, permissions still apply', function () {
    $s = app(SecuritySettings::class);
    $s->staff_two_factor_required = false;
    $s->save();

    $admin = User::factory()->staff(SystemRole::SuperAdmin)->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]); // no two-factor set up
    $support = User::factory()->staff(SystemRole::Support)->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    $support->syncRoles([]);
    $support->syncPermissions([]);

    $this->actingAs($admin)->get('/admin/listings')->assertOk();
    $this->actingAs($support)->get('/admin/listings')->assertForbidden();
});

it('flags the switched-off setting before go-live', function () {
    $s = app(SecuritySettings::class);
    $s->staff_two_factor_required = false;
    $s->save();

    expect(SecurityCheck::problems())->toHaveKey('Staff two-factor must be required');
});
