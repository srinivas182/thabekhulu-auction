<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff member was invited. The audit log (Sprint 2c) records this.
 */
final class StaffInvited
{
    use Dispatchable;

    public function __construct(
        public readonly StaffInvitation $invitation,
        public readonly User $actor,
    ) {}
}
