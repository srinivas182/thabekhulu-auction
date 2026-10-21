<?php

namespace App\Domains\Audit\Models;

use App\Domains\Audit\Support\Masker;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * An audit log entry. Entries are written once and can never be edited or
 * deleted through the application, including by super admins. Retention
 * pruning (activitylog:clean) deletes old rows directly in the database.
 *
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class AuditEntry extends Activity
{
    protected static function booted(): void
    {
        static::creating(function (AuditEntry $entry): void {
            $entry->properties = Masker::mask($entry->properties);

            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                $request = request();
                $entry->ip_address ??= $request->ip();
                $entry->user_agent ??= mb_substr((string) $request->userAgent(), 0, 255) ?: null;
            }
        });

        static::updating(fn () => throw new LogicException('Audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit entries cannot be deleted.'));
    }
}
