<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Payments\Contracts\PaymentProvider;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Domains\Payments\Support\PaymentRegistry;
use App\Domains\Verification\Enums\Outcome;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Settings → Payment providers (payments.providers). Secrets are write-only. */
class PaymentProviderController extends Controller
{
    public function __construct(private readonly PaymentRegistry $registry) {}

    public function index(): Response
    {
        return Inertia::render('admin/finance/providers', [
            'providers' => array_values(array_map(function (PaymentProvider $p): array {
                $s = $this->registry->setting($p->key());
                $saved = (array) ($s->credentials ?? []);
                $secret = (string) ($saved['callback_secret'] ?? '');

                return [
                    'key' => $p->key(), 'name' => $p->name(), 'ready' => $p->ready(), 'note' => $p->integrationNote(), 'supports' => $p->supports(),
                    'enabled' => $s->enabled, 'mode' => $s->mode, 'lastSuccess' => $s->last_success_at?->toIso8601String(), 'lastError' => $s->last_error,
                    'fields' => array_map(fn (array $f): array => $f + [
                        'value' => $f['secret'] ? null : ($saved[$f['name']] ?? ''),
                        'hint' => $f['secret'] && filled($saved[$f['name']] ?? null) ? '•••• '.substr((string) $saved[$f['name']], -4) : null,
                    ], $p->credentialFields()),
                    'callbackUrl' => $p->key() === 'tradesafe'
                        ? ($secret !== '' ? route('webhooks.payments.tradesafe', $secret) : null)
                        : route('webhooks.payments', $p->key()),
                ];
            }, $this->registry->all())),
            'depositProvider' => app(PaymentSettings::class)->deposit_provider,
        ]);
    }

    public function update(Request $request, string $provider, #[CurrentUser] User $actor): RedirectResponse
    {
        $adapter = $this->registry->find($provider) ?? abort(404);
        $data = $request->validate(['enabled' => ['boolean'], 'mode' => ['required', Rule::in(['sandbox', 'live'])], 'credentials' => ['array'], 'credentials.*' => ['nullable', 'string', 'max:500']]);

        if (($data['enabled'] ?? false) && ! $adapter->ready()) {
            return back()->withErrors(['provider' => __(':name can’t be enabled until its connection is completed.', ['name' => $adapter->name()])]);
        }

        $s = $this->registry->setting($provider);
        $current = (array) ($s->credentials ?? []);
        foreach ($adapter->credentialFields() as $f) {
            $v = $data['credentials'][$f['name']] ?? null;
            if (! $f['secret'] || filled($v)) {
                $current[$f['name']] = (string) $v;
            }
        }
        $s->fill(['enabled' => (bool) ($data['enabled'] ?? false), 'mode' => $data['mode'], 'credentials' => $current])->save();

        Audit::record('payments', 'provider_updated', "Updated {$adapter->name()} settings", null, $actor, ['attributes' => ['enabled' => $s->enabled, 'mode' => $s->mode]]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name saved.', ['name' => $adapter->name()])]);

        return back();
    }

    public function test(string $provider, #[CurrentUser] User $actor): RedirectResponse
    {
        $adapter = $this->registry->find($provider) ?? abort(404);
        $s = $this->registry->setting($provider);
        $r = $adapter->testConnection((array) ($s->credentials ?? []), $s->mode);
        $ok = $r->outcome === Outcome::Passed;
        $s->forceFill($ok ? ['last_success_at' => now(), 'last_error' => null] : ['last_error' => implode(' ', $r->reasons)])->save();

        Audit::record('payments', 'provider_tested', "Tested {$adapter->name()}: ".($ok ? 'connected' : 'failed'), null, $actor);
        Inertia::flash('toast', ['type' => $ok ? 'success' : 'error', 'message' => implode(' ', $r->reasons)]);

        return back();
    }

    public function depositProvider(Request $request, #[CurrentUser] User $actor): RedirectResponse
    {
        $key = (string) $request->validate(['deposit_provider' => ['required', 'string']])['deposit_provider'];
        if ($key !== 'none' && ! $this->registry->usable($key, 'deposit')) {
            return back()->withErrors(['deposit_provider' => __('Enable the provider and save its keys first.')]);
        }

        $s = app(PaymentSettings::class);
        $s->deposit_provider = $key;
        $s->save();

        Audit::record('payments', 'deposit_provider', "Deposit escrow provider set to {$key}", null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }
}
