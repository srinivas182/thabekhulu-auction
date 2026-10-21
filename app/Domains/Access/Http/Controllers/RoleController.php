<?php

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Access\Http\Requests\RoleRequest;
use App\Domains\Access\Support\PermissionCatalogue;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * System roles are read-only (their permissions come from the catalogue
 * and are re-applied on every deploy). Custom staff roles are created and
 * edited here.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->whereNotIn('name', [SystemRole::Buyer->value, SystemRole::Seller->value])
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'label' => (string) ($role->getAttribute('label') ?? $role->name),
                'description' => $role->getAttribute('description'),
                'system' => (bool) $role->getAttribute('is_system'),
                'users' => (int) $role->getAttribute('users_count'),
                'permissions' => (int) $role->getAttribute('permissions_count'),
            ]);

        return Inertia::render('admin/roles/index', ['roles' => $roles]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => null,
            'groups' => PermissionCatalogue::grouped(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $label = (string) $request->input('label');

        $role = Role::create(['name' => $this->uniqueName($label), 'guard_name' => 'web']);
        $role->forceFill([
            'label' => $label,
            'description' => $request->input('description'),
            'is_system' => false,
        ])->save();
        $role->syncPermissions($this->permissions($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return redirect()->route('admin.roles.index');
    }

    public function edit(Role $role): Response
    {
        $this->ensureEditable($role, allowSystem: true);

        return Inertia::render('admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'label' => (string) ($role->getAttribute('label') ?? $role->name),
                'description' => $role->getAttribute('description'),
                'system' => (bool) $role->getAttribute('is_system'),
                'permissions' => $role->permissions()->pluck('name')->values()->all(),
            ],
            'groups' => PermissionCatalogue::grouped(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureEditable($role);

        $role->forceFill([
            'label' => $request->input('label'),
            'description' => $request->input('description'),
        ])->save();
        $role->syncPermissions($this->permissions($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return redirect()->route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureEditable($role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('Move staff to another role before deleting this one.'),
            ]);
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return redirect()->route('admin.roles.index');
    }

    private function ensureEditable(Role $role, bool $allowSystem = false): void
    {
        abort_if(in_array($role->name, [SystemRole::Buyer->value, SystemRole::Seller->value], true), 404);
        abort_if(! $allowSystem && $role->getAttribute('is_system'), 403, __('System roles can’t be changed.'));
    }

    /** @return list<string> */
    private function permissions(RoleRequest $request): array
    {
        /** @var list<string> $permissions */
        $permissions = array_values(array_unique((array) $request->input('permissions', [])));

        return $permissions;
    }

    private function uniqueName(string $label): string
    {
        $base = Str::slug($label) ?: 'role';
        $name = $base;
        $suffix = 2;

        while (Role::where('name', $name)->exists()) {
            $name = "{$base}-{$suffix}";
            $suffix++;
        }

        return $name;
    }
}
