<?php

namespace Database\Seeders;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Support\PermissionCatalogue;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds every permission and the system roles. Idempotent: safe to run on
 * every deploy. Permissions added to custom roles by admins are kept.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalogue::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::findOrCreate($systemRole->value, 'web');
            $role->forceFill([
                'label' => $systemRole->label(),
                'description' => $systemRole->description(),
                'is_system' => true,
            ])->save();

            // System role permissions are owned by the catalogue.
            $role->syncPermissions(PermissionCatalogue::forRole($systemRole));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
