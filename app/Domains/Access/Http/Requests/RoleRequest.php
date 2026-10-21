<?php

namespace App\Domains\Access\Http\Requests;

use App\Domains\Access\Support\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');
        $ignoreId = is_object($role) && isset($role->id) ? $role->id : null;

        return [
            'label' => ['required', 'string', 'max:100', Rule::unique('roles', 'label')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalogue::all())],
        ];
    }
}
