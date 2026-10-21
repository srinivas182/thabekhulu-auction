<?php

namespace App\Domains\Identity\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff account was deactivated. The audit log (Sprint 2c) records this.
 */
final class StaffDeactivated
{
    use Dispatchable;

    public function __construct(
        public readonly User $staff,
        public readonly User $actor,
    ) {}
}
