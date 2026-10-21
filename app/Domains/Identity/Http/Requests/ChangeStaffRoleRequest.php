<?php

namespace App\Domains\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStaffRoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::exists('roles', 'name')->whereNotIn('name', ['buyer', 'seller'])],
        ];
    }
}
