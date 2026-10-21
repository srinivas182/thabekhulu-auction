<?php

namespace App\Domains\Audit\Support;

use App\Domains\Audit\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Applies the audit viewer's filters. Shared by the page and the CSV export
 * so both always return the same entries.
 */
final class AuditQuery
{
    /**
     * @return array{user: string, module: string, action: string, from: string, to: string}
     */
    public static function filters(Request $request): array
    {
        return [
            'user' => trim((string) $request->query('user', '')),
            'module' => (string) $request->query('module', ''),
            'action' => trim((string) $request->query('action', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];
    }

    /**
     * @param  array{user: string, module: string, action: string, from: string, to: string}  $filters
     * @return Builder<AuditEntry>
     */
    public static function build(array $filters): Builder
    {
        $tz = (string) config('platform.display_timezone');

        return AuditEntry::query()
            ->with('causer')
            ->when($filters['module'] !== '', fn (Builder $q) => $q->where('log_name', $filters['module']))
            ->when($filters['action'] !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('event', 'like', "%{$filters['action']}%")
                ->orWhere('description', 'like', "%{$filters['action']}%")))
            ->when($filters['user'] !== '', fn (Builder $q) => $q->whereHasMorph('causer', [User::class], fn (Builder $u) => $u
                ->where('email', 'like', "%{$filters['user']}%")
                ->orWhere('first_name', 'like', "%{$filters['user']}%")
                ->orWhere('last_name', 'like', "%{$filters['user']}%")))
            ->when(self::date($filters['from'], $tz), fn (Builder $q, Carbon $d) => $q->where('created_at', '>=', $d->startOfDay()->utc()))
            ->when(self::date($filters['to'], $tz), fn (Builder $q, Carbon $d) => $q->where('created_at', '<=', $d->endOfDay()->utc()))
            ->latest('id');
    }

    /**
     * @return array{id: int, at: string|null, module: string|null, event: string|null, description: string, actor: string, actorEmail: string|null, ip: string|null, changes: array<string, array{old: mixed, new: mixed}>, extra: array<string, mixed>}
     */
    public static function present(AuditEntry $entry): array
    {
        $properties = collect($entry->properties)->toArray();
        $old = (array) ($properties['old'] ?? []);
        $new = (array) ($properties['attributes'] ?? []);
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $changes[(string) $key] = ['old' => $old[$key] ?? null, 'new' => $new[$key] ?? null];
        }

        $causer = $entry->causer;

        return [
            'id' => (int) $entry->id,
            'at' => $entry->created_at?->toIso8601String(),
            'module' => $entry->log_name,
            'event' => $entry->event,
            'description' => (string) $entry->description,
            'actor' => $causer instanceof User ? $causer->name : 'System or visitor',
            'actorEmail' => $causer instanceof User ? $causer->email : null,
            'ip' => $entry->ip_address,
            'changes' => $changes,
            'extra' => array_diff_key($properties, ['old' => true, 'attributes' => true]),
        ];
    }

    private static function date(string $value, string $tz): ?Carbon
    {
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value, $tz) ?: null;
    }
}
