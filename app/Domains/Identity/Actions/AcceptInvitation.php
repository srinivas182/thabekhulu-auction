<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserType;
use App\Domains\Identity\Events\StaffInvitationAccepted;
use App\Domains\Identity\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a valid, unused invitation into a staff account. The email is
 * treated as verified because the person received the link there.
 */
class AcceptInvitation
{
    /**
     * @param  array{first_name: string, last_name: string, mobile: string, password: string}  $details
     */
    public function handle(StaffInvitation $invitation, array $details): User
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => __('This invitation has expired or has already been used.'),
            ]);
        }

        return DB::transaction(function () use ($invitation, $details): User {
            // Lock the row so a double-submit cannot create two accounts.
            $fresh = StaffInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! $fresh->isPending() || User::where('email', $fresh->email)->exists()) {
                throw ValidationException::withMessages([
                    'token' => __('This invitation has expired or has already been used.'),
                ]);
            }

            $user = new User([
                'first_name' => $details['first_name'],
                'last_name' => $details['last_name'],
                'mobile' => $details['mobile'],
                'email' => $fresh->email,
                'password' => $details['password'],
            ]);
            $user->forceFill([
                'type' => UserType::Staff,
                'email_verified_at' => now(),
            ])->save();

            $user->assignRole($fresh->role);

            $fresh->forceFill(['accepted_at' => now(), 'accepted_user_id' => $user->id])->save();

            StaffInvitationAccepted::dispatch($fresh, $user);

            return $user;
        });
    }
}
