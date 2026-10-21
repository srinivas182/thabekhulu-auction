<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Settings\Support\SettingsRegistry;
use App\Models\User;
use Spatie\LaravelSettings\Settings;

/**
 * Saves a settings group and records exactly what changed. Read-only
 * fields are never written, whatever the request contains.
 */
class UpdateSettings
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}> the changes made
     */
    public function handle(User $actor, string $group, array $values): array
    {
        $definition = SettingsRegistry::find($group);
        abort_if($definition === null, 404);

        /** @var Settings $settings */
        $settings = app($definition['class']);
        $changes = [];

        foreach ($definition['fields'] as $field => $meta) {
            if (($meta['readOnly'] ?? false) || ! array_key_exists($field, $values)) {
                continue;
            }

            $old = $settings->{$field};
            $new = $values[$field];

            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
                $settings->{$field} = $new;
            }
        }

        if ($changes === []) {
            return [];
        }

        $settings->save();

        Audit::record('settings', 'updated', "Changed {$definition['label']} settings", null, $actor, [
            'group' => $group,
            'old' => array_map(fn (array $c) => $c['old'], $changes),
            'attributes' => array_map(fn (array $c) => $c['new'], $changes),
        ]);

        return $changes;
    }
}
