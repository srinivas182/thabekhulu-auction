<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Rules for a user's profile details.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'mobile' => $this->mobileRules(),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:100'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'lowercase',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * South African mobile numbers: 0821234567, 082 123 4567 or +27821234567.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function mobileRules(): array
    {
        return ['required', 'string', 'regex:/^(\+27|0)[6-8][0-9]{8}$/'];
    }

    /**
     * Normalise a South African mobile number to E.164 (+27...).
     */
    protected function normaliseMobile(string $mobile): string
    {
        $digits = preg_replace('/[\s\-()]/', '', $mobile) ?? $mobile;

        return str_starts_with($digits, '0') ? '+27'.substr($digits, 1) : $digits;
    }
}
