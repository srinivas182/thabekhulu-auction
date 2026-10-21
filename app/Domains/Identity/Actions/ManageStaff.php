<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Events\StaffDeactivated;
use App\Domains\Identity\Events\StaffInvitationRevoked;
use App\Domains\Identity\Events\StaffReactivated;
use App\Domains\Identity\Events\StaffRoleChanged;
use App\Domains\Identity\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Staff lifecycle rules: deactivate, reactivate, change role, revoke
 * invitation. Staff are never deleted; the platform always keeps at least
 * one active super admin.
 */
class ManageStaff
{
    public function deactivate(User $actor, User $staff): void
    {
        $this->ensureStaff($staff);

        if ($actor->is($staff)) {
            throw ValidationException::withMessages(['staff' => __('You can’t deactivate your own account.')]);
        }

        $this->ensureAnotherActiveSuperAdmin($staff);

        DB::transaction(function () use ($staff): void {
            $staff->forceFill(['deactivated_at' => now(), 'remember_token' => null])->save();

            // Remove stored sessions so every device is signed out at once.
            DB::table('sessions')->where('user_id', $staff->id)->delete();
        });

        StaffDeactivated::dispatch($staff, $actor);
    }

    public function reactivate(User $actor, User $staff): void
    {
        $this->ensureStaff($staff);

        $staff->forceFill(['deactivated_at' => null])->save();

        StaffReactivated::dispatch($staff, $actor);
    }

    public function changeRole(User $actor, User $staff, Role $role): void
    {
        $this->ensureStaff($staff);

        if (in_array($role->name, [SystemRole::Buyer->value, SystemRole::Seller->value], true)) {
            throw ValidationException::withMessages(['role' => __('Choose a staff role.')]);
        }

        $from = (string) ($staff->getRoleNames()->first() ?? '');

        if ($from === $role->name) {
            return;
        }

        if ($from === SystemRole::SuperAdmin->value) {
            $this->ensureAnotherActiveSuperAdmin($staff);
        }

        $staff->syncRoles([$role]);

        StaffRoleChanged::dispatch($staff, $from, $role->name, $actor);
    }

    public function revokeInvitation(User $actor, StaffInvitation $invitation): void
    {
        if (! $invitation->isPending()) {
            return;
        }

        $invitation->forceFill(['revoked_at' => now()])->save();

        StaffInvitationRevoked::dispatch($invitation, $actor);
    }

    private function ensureStaff(User $user): void
    {
        abort_unless($user->isStaff(), 404);
    }

    private function ensureAnotherActiveSuperAdmin(User $leaving): void
    {
        if (! $leaving->isSuperAdmin()) {
            return;
        }

        $others = User::role(SystemRole::SuperAdmin->value)
            ->whereNull('deactivated_at')
            ->whereKeyNot($leaving->id)
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages([
                'staff' => __('At least one active super admin is required.'),
            ]);
        }
    }
}
