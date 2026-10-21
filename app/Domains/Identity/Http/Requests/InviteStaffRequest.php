<?php

namespace App\Domains\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->whereNotIn('name', ['buyer', 'seller'])],
        ];
    }
}
