<?php

namespace Database\Seeders;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first super admin if none exists. The generated password is
 * printed once; the account must enrol two-factor authentication on first
 * login before it can use the back office.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::role(SystemRole::SuperAdmin->value)->exists()) {
            return;
        }

        $email = (string) config('platform.super_admin_email');
        $password = Str::password(20);

        $user = User::query()->forceCreate([
            'type' => UserType::Staff,
            'first_name' => 'Platform',
            'last_name' => 'Administrator',
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $user->assignRole(SystemRole::SuperAdmin->value);

        $this->command?->warn('Super admin created. Save these credentials now; they are shown only once.');
        $this->command?->line("  Email:    {$email}");
        $this->command?->line("  Password: {$password}");
    }
}
