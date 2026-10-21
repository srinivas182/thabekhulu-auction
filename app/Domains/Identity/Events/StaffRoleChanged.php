<?php

namespace App\Domains\Identity\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff member's role was changed. The audit log (Sprint 2c) records this.
 */
final class StaffRoleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly User $staff,
        public readonly string $from,
        public readonly string $to,
        public readonly User $actor,
    ) {}
}
