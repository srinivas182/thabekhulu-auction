<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Audit\Support\Audit;
use App\Domains\Identity\Actions\InviteStaff;
use App\Domains\Identity\Actions\ManageStaff;
use App\Domains\Identity\Enums\UserType;
use App\Domains\Identity\Http\Requests\ChangeStaffRoleRequest;
use App\Domains\Identity\Http\Requests\InviteStaffRequest;
use App\Domains\Identity\Models\StaffInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(): Response
    {
        $staff = User::staff()
            ->with('roles:id,name,label')
            ->orderByRaw('deactivated_at is not null')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user): array => $this->presentStaff($user))
            ->all();

        $invitations = StaffInvitation::pending()
            ->with(['role:id,name,label', 'inviter:id,first_name,last_name'])
            ->latest()
            ->get()
            ->map(fn (StaffInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'roleLabel' => $invitation->role->getAttribute('label') ?? $invitation->role->name,
                'invitedBy' => $invitation->inviter->name,
                'expiresAt' => $invitation->expires_at->toIso8601String(),
            ]);

        return Inertia::render('admin/staff/index', [
            'staff' => $staff,
            'invitations' => $invitations,
            'roles' => $this->staffRoleOptions(),
        ]);
    }

    public function invite(InviteStaffRequest $request, #[CurrentUser] User $actor, InviteStaff $invite): RedirectResponse
    {
        $invite->handle($actor, (string) $request->input('email'), $this->role((string) $request->input('role')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function changeRole(ChangeStaffRoleRequest $request, User $user, #[CurrentUser] User $actor, ManageStaff $manage): RedirectResponse
    {
        $manage->changeRole($actor, $user, $this->role((string) $request->input('role')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function deactivate(User $user, #[CurrentUser] User $actor, ManageStaff $manage): RedirectResponse
    {
        $manage->deactivate($actor, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name has been deactivated and signed out.', ['name' => $user->name])]);

        return back();
    }

    public function reactivate(User $user, #[CurrentUser] User $actor, ManageStaff $manage): RedirectResponse
    {
        $manage->reactivate($actor, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name has been reactivated.', ['name' => $user->name])]);

        return back();
    }

    public function revokeInvitation(StaffInvitation $invitation, #[CurrentUser] User $actor, ManageStaff $manage): RedirectResponse
    {
        $manage->revokeInvitation($actor, $invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }

    /**
     * @return array{ulid: string, name: string, email: string, role: string|null, roleLabel: string|null, twoFactor: bool, active: bool, lastLoginAt: string|null}
     */
    private function presentStaff(User $user): array
    {
        $role = $user->roles->first();
        $roleName = $role?->getAttribute('name');
        $roleLabel = $role?->getAttribute('label') ?? $roleName;

        return [
            'ulid' => $user->ulid,
            'name' => $user->name,
            'email' => $user->email,
            'role' => is_string($roleName) ? $roleName : null,
            'roleLabel' => is_string($roleLabel) ? $roleLabel : null,
            'twoFactor' => $user->hasConfirmedTwoFactor(),
            'active' => $user->isActive(),
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
        ];
    }

    private function role(string $name): Role
    {
        return Role::query()->where('name', $name)->where('guard_name', 'web')->firstOrFail();
    }

    /** @return list<array{name: string, label: string}> */
    private function staffRoleOptions(): array
    {
        return array_values(Role::query()
            ->whereNotIn('name', [SystemRole::Buyer->value, SystemRole::Seller->value])
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->get(['name', 'label'])
            ->map(fn (Role $role): array => ['name' => $role->name, 'label' => (string) ($role->getAttribute('label') ?? $role->name)])
            ->all());
    }

    /**
     * Create a staff account directly, for when email isn't set up. The system generates the temporary
     * password (shown once); the person must choose their own at first sign-in.
     */
    public function create(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'], 'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email:rfc', 'max:160', 'unique:users,email'],
            'role' => ['required', Rule::in(array_column($this->staffRoleOptions(), 'name'))],
        ], ['email.unique' => __('Someone already has an account with this email address.')]);
        $role = $this->role($data['role']);
        abort_if($role->name === SystemRole::SuperAdmin->value && ! $actor->hasRole(SystemRole::SuperAdmin->value), 403);

        $temporary = Str::password(16, symbols: false);
        $user = new User(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => mb_strtolower($data['email']), 'password' => $temporary]);
        $user->forceFill(['type' => UserType::Staff, 'email_verified_at' => now(), 'must_change_password' => true])->save();
        $user->assignRole($role);
        Audit::record('staff', 'created_with_password', "Created staff account for {$user->email} ({$role->name}) with a temporary password", $user, $actor);

        // Shown once to the person who created the account; never stored in readable form.
        Inertia::flash('temporaryPassword', ['email' => $user->email, 'password' => $temporary]);

        return back();
    }

    /**
     * Issue a new temporary password for a staff member (for example, if the first one was lost).
     * Shown once; they're signed out everywhere and must choose their own at the next sign-in.
     */
    public function temporaryPassword(User $user, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_unless($user->type === UserType::Staff, 404);
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages(['staff' => __('Change your own password in Security settings instead.')]);
        }
        abort_if($user->hasRole(SystemRole::SuperAdmin->value) && ! $actor->hasRole(SystemRole::SuperAdmin->value), 403);

        $temporary = Str::password(16, symbols: false);
        $user->forceFill(['password' => $temporary, 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Audit::record('staff', 'temporary_password_issued', "Issued a new temporary password for {$user->email}", $user, $actor);

        Inertia::flash('temporaryPassword', ['email' => $user->email, 'password' => $temporary]);

        return back();
    }
}
