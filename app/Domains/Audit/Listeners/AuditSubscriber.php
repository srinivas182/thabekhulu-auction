<?php

namespace App\Domains\Audit\Listeners;

use App\Domains\Audit\Support\Audit;
use App\Domains\Identity\Events\StaffDeactivated;
use App\Domains\Identity\Events\StaffInvitationAccepted;
use App\Domains\Identity\Events\StaffInvitationRevoked;
use App\Domains\Identity\Events\StaffInvited;
use App\Domains\Identity\Events\StaffReactivated;
use App\Domains\Identity\Events\StaffRoleChanged;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Turns authentication and staff lifecycle events into audit entries
 * (spec 5.2 and 8). Model changes are logged by the models themselves.
 */
class AuditSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, function (Login $e): void {
            Audit::record('auth', 'login', 'Signed in', $this->user($e->user), $this->user($e->user));
        });
        $events->listen(Logout::class, function (Logout $e): void {
            if ($e->user !== null) {
                Audit::record('auth', 'logout', 'Signed out', $this->user($e->user), $this->user($e->user));
            }
        });
        $events->listen(Failed::class, function (Failed $e): void {
            Audit::record('auth', 'login_failed', 'Failed sign-in attempt', $this->user($e->user), null, [
                'email' => $e->credentials['email'] ?? null,
            ]);
        });
        $events->listen(Lockout::class, function (Lockout $e): void {
            Audit::record('auth', 'lockout', 'Sign-in locked after too many attempts', null, null, [
                'email' => $e->request->input('email'),
            ]);
        });
        $events->listen(Registered::class, function (Registered $e): void {
            Audit::record('auth', 'registered', 'Buyer account created', $this->user($e->user), $this->user($e->user));
        });
        $events->listen(Verified::class, function (Verified $e): void {
            Audit::record('auth', 'email_verified', 'Email address verified', $this->user($e->user), $this->user($e->user));
        });
        $events->listen(PasswordReset::class, function (PasswordReset $e): void {
            Audit::record('auth', 'password_reset', 'Password reset by email link', $this->user($e->user), $this->user($e->user));
        });
        $events->listen(TwoFactorAuthenticationConfirmed::class, function (TwoFactorAuthenticationConfirmed $e): void {
            Audit::record('auth', 'two_factor_enabled', 'Two-factor authentication turned on', $this->user($e->user), $this->user($e->user));
        });
        $events->listen(TwoFactorAuthenticationDisabled::class, function (TwoFactorAuthenticationDisabled $e): void {
            Audit::record('auth', 'two_factor_disabled', 'Two-factor authentication turned off', $this->user($e->user), $this->user($e->user));
        });
        $events->listen(RecoveryCodesGenerated::class, function (RecoveryCodesGenerated $e): void {
            Audit::record('auth', 'recovery_codes_generated', 'New two-factor recovery codes generated', $this->user($e->user), $this->user($e->user));
        });

        $events->listen(StaffInvited::class, function (StaffInvited $e): void {
            Audit::record('staff', 'invited', "Invited {$e->invitation->email} as {$e->invitation->role->getAttribute('label')}", null, $e->actor, [
                'email' => $e->invitation->email,
                'role' => $e->invitation->role->name,
            ]);
        });
        $events->listen(StaffInvitationAccepted::class, function (StaffInvitationAccepted $e): void {
            Audit::record('staff', 'invitation_accepted', 'Accepted staff invitation', $e->user, $e->user);
        });
        $events->listen(StaffInvitationRevoked::class, function (StaffInvitationRevoked $e): void {
            Audit::record('staff', 'invitation_revoked', "Revoked invitation for {$e->invitation->email}", null, $e->actor, [
                'email' => $e->invitation->email,
            ]);
        });
        $events->listen(StaffDeactivated::class, function (StaffDeactivated $e): void {
            Audit::record('staff', 'deactivated', "Deactivated {$e->staff->name}", $e->staff, $e->actor);
        });
        $events->listen(StaffReactivated::class, function (StaffReactivated $e): void {
            Audit::record('staff', 'reactivated', "Reactivated {$e->staff->name}", $e->staff, $e->actor);
        });
        $events->listen(StaffRoleChanged::class, function (StaffRoleChanged $e): void {
            Audit::record('staff', 'role_changed', "Changed {$e->staff->name}’s role", $e->staff, $e->actor, [
                'old' => ['role' => $e->from],
                'attributes' => ['role' => $e->to],
            ]);
        });
    }

    private function user(mixed $user): ?User
    {
        return $user instanceof User ? $user : null;
    }
}
