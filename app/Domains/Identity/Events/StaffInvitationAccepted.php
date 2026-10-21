<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invitation was accepted and a staff account created. The audit log (Sprint 2c) records this.
 */
final class StaffInvitationAccepted
{
    use Dispatchable;

    public function __construct(
        public readonly StaffInvitation $invitation,
        public readonly User $user,
    ) {}
}
