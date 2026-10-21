<?php

namespace App\Domains\Identity\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        $this->merge(['mobile' => preg_replace('/[\s\-()]/', '', (string) $this->input('mobile'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'mobile' => $this->mobileRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /** @return array{first_name: string, last_name: string, mobile: string, password: string} */
    public function details(): array
    {
        return [
            'first_name' => (string) $this->input('first_name'),
            'last_name' => (string) $this->input('last_name'),
            'mobile' => $this->normaliseMobile((string) $this->input('mobile')),
            'password' => (string) $this->input('password'),
        ];
    }
}
