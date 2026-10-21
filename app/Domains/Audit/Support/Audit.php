<?php

namespace App\Domains\Audit\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * One place to write audit entries.
 *
 * $module groups entries in the viewer (auth, staff, roles, settings,
 * features, theme...). $event is a short machine name; $description is
 * the sentence shown to admins.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $module,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $logger = activity($module)->event($event)->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $causer ??= auth()->user();

        if ($causer !== null) {
            $logger->causedBy($causer);
        } else {
            $logger->byAnonymous();
        }

        $logger->log($description);
    }
}
