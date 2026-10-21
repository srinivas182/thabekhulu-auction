<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Events\StaffInvited;
use App\Domains\Identity\Models\StaffInvitation;
use App\Domains\Identity\Notifications\StaffInvitationNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Invites a person to the back office with a staff role. A new invitation
 * to the same email replaces any earlier pending one.
 */
class InviteStaff
{
    public function handle(User $actor, string $email, Role $role): StaffInvitation
    {
        $email = strtolower(trim($email));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('An account with this email already exists.'),
            ]);
        }

        $this->ensureStaffRole($role);

        $token = Str::random(64);
        $ttl = (int) config('platform.invitation_ttl_hours');

        $invitation = DB::transaction(function () use ($actor, $email, $role, $token, $ttl): StaffInvitation {
            StaffInvitation::pending()->where('email', $email)->update(['revoked_at' => now()]);

            return StaffInvitation::create([
                'email' => $email,
                'role_id' => $role->id,
                'token_hash' => StaffInvitation::hashToken($token),
                'invited_by' => $actor->id,
                'expires_at' => now()->addHours($ttl),
            ]);
        });

        Notification::route('mail', $email)->notify(new StaffInvitationNotification(
            token: $token,
            roleLabel: (string) ($role->getAttribute('label') ?? $role->name),
            inviterName: $actor->name,
            ttlHours: $ttl,
        ));

        StaffInvited::dispatch($invitation, $actor);

        return $invitation;
    }

    private function ensureStaffRole(Role $role): void
    {
        if (in_array($role->name, ['buyer', 'seller'], true)) {
            throw ValidationException::withMessages([
                'role' => __('Choose a staff role.'),
            ]);
        }
    }
}
