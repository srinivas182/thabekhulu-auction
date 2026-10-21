<?php

namespace App\Domains\Settings\Http\Controllers;

use App\Domains\Settings\Actions\UpdateSettings;
use App\Domains\Settings\Support\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelSettings\Settings;
use Symfony\Component\HttpFoundation\IpUtils;

class SettingsController extends Controller
{
    public function edit(Request $request, string $group = 'general'): Response
    {
        $definition = SettingsRegistry::find($group);
        abort_if($definition === null, 404);

        /** @var Settings $settings */
        $settings = app($definition['class']);

        return Inertia::render('admin/settings/edit', [
            'group' => $group,
            'groups' => collect(SettingsRegistry::groups())
                ->map(fn (array $g, string $key): array => ['key' => $key, 'label' => $g['label']])
                ->values(),
            'definition' => [
                'label' => $definition['label'],
                'description' => $definition['description'],
                'fields' => collect($definition['fields'])
                    ->map(fn (array $meta, string $name): array => [
                        'name' => $name,
                        'type' => $meta['type'],
                        'label' => $meta['label'],
                        'help' => $meta['help'] ?? null,
                        'prefix' => $meta['prefix'] ?? null,
                        'suffix' => $meta['suffix'] ?? null,
                        'readOnly' => $meta['readOnly'] ?? false,
                    ])
                    ->values(),
            ],
            'values' => $settings->toArray(),
            'canManage' => Gate::allows('settings.manage'),
            'currentIp' => $request->ip(),
        ]);
    }

    public function update(Request $request, string $group, #[CurrentUser] User $actor, UpdateSettings $update): RedirectResponse
    {
        $definition = SettingsRegistry::find($group);
        abort_if($definition === null, 404);

        $input = $this->normalise($request, $definition['fields']);

        $rules = [];
        foreach ($definition['fields'] as $field => $meta) {
            if ($meta['readOnly'] ?? false) {
                continue;
            }
            $rules[$field] = $meta['rules'];
            if (isset($meta['itemRules'])) {
                $rules["{$field}.*"] = $meta['itemRules'];
            }
        }

        $validated = validator($input, $rules)->validate();

        if ($group === 'security') {
            $this->ensureAllowListKeepsCurrentIp($request, $validated['admin_ip_allowlist'] ?? []);
        }

        $changes = $update->handle($actor, $group, $validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes === [] ? __('No changes to save.') : __('Settings saved.'),
        ]);

        return back();
    }

    /**
     * Converts form input to the setting types: numbers, booleans and
     * one-per-line lists.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function normalise(Request $request, array $fields): array
    {
        $input = [];

        foreach ($fields as $field => $meta) {
            $value = $request->input($field);

            $input[$field] = match ($meta['type']) {
                'number' => is_numeric($value) ? (int) $value : $value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
                'list' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value) ?: []))),
                default => $value === null ? '' : $value,
            };
        }

        return $input;
    }

    /**
     * Prevents an admin from locking themselves out of the back office.
     *
     * @param  list<string>  $list
     */
    private function ensureAllowListKeepsCurrentIp(Request $request, array $list): void
    {
        foreach ($list as $entry) {
            if (! filter_var(explode('/', $entry)[0], FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['admin_ip_allowlist' => __('“:entry” is not a valid IP address or range.', ['entry' => $entry])]);
            }
        }

        if ($list !== [] && ! IpUtils::checkIp((string) $request->ip(), $list)) {
            throw ValidationException::withMessages([
                'admin_ip_allowlist' => __('Add your current address (:ip) to the list, or you would be locked out.', ['ip' => $request->ip()]),
            ]);
        }
    }
}
