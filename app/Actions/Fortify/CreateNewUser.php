<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Public sign-up creates buyer accounts only. Staff join by invitation.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['mobile'] = isset($input['mobile']) ? (preg_replace('/[\s\-()]/', '', $input['mobile']) ?? '') : '';
        $input['email'] = isset($input['email']) ? strtolower(trim($input['email'])) : '';

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('Please accept the Terms of Use and Privacy Notice to create an account.'),
            'mobile.regex' => __('Enter a South African mobile number, for example 082 123 4567.'),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = new User([
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'mobile' => $this->normaliseMobile($input['mobile']),
                'password' => $input['password'],
            ]);

            $user->forceFill([
                'type' => UserType::Buyer,
                'terms_version' => (string) config('platform.terms_version'),
                'terms_accepted_at' => now(),
                'popia_consent_at' => now(),
                'attribution' => session('attribution'), // first-touch campaign source (Sprint 8b)
            ])->save();

            $user->assignRole(SystemRole::Buyer->value);

            return $user;
        });
    }
}
