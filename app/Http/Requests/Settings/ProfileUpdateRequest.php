<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => preg_replace('/[\s\-()]/', '', (string) $this->input('mobile')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()?->id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['mobile.regex' => __('Enter a South African mobile number, for example 082 123 4567.')];
    }

    public function normalisedMobile(): string
    {
        return $this->normaliseMobile((string) $this->input('mobile'));
    }
}
