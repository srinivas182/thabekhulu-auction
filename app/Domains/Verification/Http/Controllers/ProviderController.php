<?php

namespace App\Domains\Verification\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Verification\Contracts\VerificationProvider;
use App\Domains\Verification\Enums\CheckType;
use App\Domains\Verification\Enums\Outcome;
use App\Domains\Verification\Support\ProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Verification providers (verification.providers). Secrets are
 * write-only: the page shows only their last four characters.
 */
class ProviderController extends Controller
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    public function index(): Response
    {
        return Inertia::render('admin/verification/providers', [
            'providers' => array_values(array_map(function (VerificationProvider $p): array {
                $setting = $this->registry->setting($p->key());
                $saved = (array) ($setting->credentials ?? []);

                return [
                    'key' => $p->key(), 'name' => $p->name(), 'ready' => $p->ready(), 'note' => $p->integrationNote(),
                    'supports' => array_map(fn (CheckType $c) => $c->value, $p->supports()),
                    'enabled' => $setting->enabled, 'mode' => $setting->mode,
                    'fields' => array_map(fn (array $f): array => $f + [
                        'value' => $f['secret'] ? null : ($saved[$f['name']] ?? ''),
                        'hint' => $f['secret'] && filled($saved[$f['name']] ?? null) ? '•••• '.substr((string) $saved[$f['name']], -4) : null,
                    ], $p->credentialFields()),
                    'lastSuccess' => $setting->last_success_at?->toIso8601String(), 'lastError' => $setting->last_error,
                    'webhookUrl' => route('webhooks.verification', $p->key()),
                ];
            }, $this->registry->all())),
            'checks' => collect(CheckType::cases())->map(fn (CheckType $c) => ['value' => $c->value, 'label' => $c->label()]),
            'routes' => $this->registry->routes(),
        ]);
    }

    public function update(Request $request, string $provider, #[CurrentUser] User $actor): RedirectResponse
    {
        $adapter = $this->registry->find($provider) ?? abort(404);
        $data = $request->validate([
            'enabled' => ['boolean'], 'mode' => ['required', Rule::in(['sandbox', 'live'])],
            'credentials' => ['array'], 'credentials.*' => ['nullable', 'string', 'max:500'],
        ]);

        $setting = $this->registry->setting($provider);
        $current = (array) ($setting->credentials ?? []);
        foreach ($adapter->credentialFields() as $field) {
            $value = $data['credentials'][$field['name']] ?? null;
            // Secrets left blank keep their saved value.
            if (! $field['secret'] || filled($value)) {
                $current[$field['name']] = (string) $value;
            }
        }

        $enable = (bool) ($data['enabled'] ?? false);
        if ($enable && ! $adapter->ready()) {
            return back()->withErrors(['provider' => __(':name can’t be enabled until its connection is completed.', ['name' => $adapter->name()])]);
        }

        $before = ['enabled' => $setting->enabled, 'mode' => $setting->mode];
        $setting->fill(['enabled' => $enable, 'mode' => $data['mode'], 'credentials' => $current])->save();

        Audit::record('verification', 'provider_updated', "Updated {$adapter->name()} settings", null, $actor, [
            'old' => $before, 'attributes' => ['enabled' => $enable, 'mode' => $data['mode'], 'credentials_changed' => array_keys(array_filter((array) ($data['credentials'] ?? [])))],
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name saved.', ['name' => $adapter->name()])]);

        return back();
    }

    public function test(string $provider, #[CurrentUser] User $actor): RedirectResponse
    {
        $adapter = $this->registry->find($provider) ?? abort(404);
        $setting = $this->registry->setting($provider);
        $result = $adapter->testConnection((array) ($setting->credentials ?? []), $setting->mode);

        $ok = $result->outcome === Outcome::Passed;
        $setting->forceFill($ok ? ['last_success_at' => now(), 'last_error' => null] : ['last_error' => implode(' ', $result->reasons)])->save();

        Audit::record('verification', 'provider_tested', "Tested {$adapter->name()} connection: ".($ok ? 'connected' : 'failed'), null, $actor);
        Inertia::flash('toast', ['type' => $ok ? 'success' : 'error', 'message' => implode(' ', $result->reasons)]);

        return back();
    }

    public function routes(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['routes' => ['required', 'array'], 'routes.*' => ['required', 'string']]);
        $errors = [];

        foreach (CheckType::cases() as $check) {
            $key = (string) ($data['routes'][$check->value] ?? ProviderRegistry::MANUAL);
            if ($key !== ProviderRegistry::MANUAL && ! $this->registry->usable($key, $check)) {
                $errors["routes.{$check->value}"] = __('Enable the provider and save its keys first, or choose manual review.');

                continue;
            }
            $this->registry->route($check, $key);
        }

        if ($errors !== []) {
            return back()->withErrors($errors);
        }

        Audit::record('verification', 'routes_updated', 'Updated verification routing', null, $actor, ['attributes' => $this->registry->routes()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routing saved.')]);

        return back();
    }
}
