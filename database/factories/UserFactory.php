<?php

namespace Database\Factories;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => UserType::Buyer,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '+2782'.fake()->numerify('#######'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'terms_version' => '1.0',
            'terms_accepted_at' => now(),
            'popia_consent_at' => now(),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => ['deactivated_at' => now()]);
    }

    /**
     * Staff member with the given role and confirmed 2FA (required for staff).
     */
    public function staff(SystemRole|string $role = SystemRole::SuperAdmin): static
    {
        $roleName = $role instanceof SystemRole ? $role->value : $role;

        return $this->withTwoFactor()
            ->state(fn (array $attributes) => ['type' => UserType::Staff])
            ->afterCreating(function (User $user) use ($roleName): void {
                Role::findOrCreate($roleName, 'web');
                $user->assignRole($roleName);
            });
    }
}
