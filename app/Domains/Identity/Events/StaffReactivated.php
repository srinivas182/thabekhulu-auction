<?php

namespace App\Domains\Identity\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff account was reactivated. The audit log (Sprint 2c) records this.
 */
final class StaffReactivated
{
    use Dispatchable;

    public function __construct(
        public readonly User $staff,
        public readonly User $actor,
    ) {}
}
