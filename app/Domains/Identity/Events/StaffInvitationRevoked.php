<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A pending invitation was revoked. The audit log (Sprint 2c) records this.
 */
final class StaffInvitationRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly StaffInvitation $invitation,
        public readonly User $actor,
    ) {}
}
