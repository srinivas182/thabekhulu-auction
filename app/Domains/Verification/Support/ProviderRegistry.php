<?php

namespace App\Domains\Verification\Support;

use App\Domains\Verification\Contracts\VerificationProvider;
use App\Domains\Verification\Enums\CheckType;
use App\Domains\Verification\Models\ProviderSetting;
use App\Domains\Verification\Providers\SmileIdProvider;
use App\Domains\Verification\Providers\SumsubProvider;
use App\Domains\Verification\Providers\VerifyIdProvider;
use Illuminate\Support\Facades\DB;

/**
 * The built-in providers, their settings and the check routing (decision K1).
 * "manual" is always available and sends a check to the KYC review queue.
 */
final class ProviderRegistry
{
    public const MANUAL = 'manual';

    /** @return array<string, VerificationProvider> */
    public function all(): array
    {
        $providers = [app(SmileIdProvider::class), app(VerifyIdProvider::class), app(SumsubProvider::class)];

        return array_combine(array_map(fn (VerificationProvider $p) => $p->key(), $providers), $providers);
    }

    public function find(string $key): ?VerificationProvider
    {
        return $this->all()[$key] ?? null;
    }

    public function setting(string $key): ProviderSetting
    {
        return ProviderSetting::firstOrCreate(['key' => $key], ['enabled' => false, 'mode' => 'sandbox']);
    }

    /** True when the provider is enabled, ready, has credentials and supports the check. */
    public function usable(string $key, CheckType $check): bool
    {
        $provider = $this->find($key);
        if ($provider === null || ! $provider->ready() || ! in_array($check, $provider->supports(), true)) {
            return false;
        }

        $setting = ProviderSetting::where('key', $key)->first();

        return $setting !== null && $setting->enabled && ! empty($setting->credentials);
    }

    /** The provider routed for a check, or null for manual review (also when the routed provider is no longer usable). */
    public function forCheck(CheckType $check): ?VerificationProvider
    {
        $key = DB::table('verification_routes')->where('check_type', $check->value)->value('provider_key');

        return is_string($key) && $key !== self::MANUAL && $this->usable($key, $check) ? $this->find($key) : null;
    }

    public function route(CheckType $check, string $key): void
    {
        DB::table('verification_routes')->updateOrInsert(['check_type' => $check->value], ['provider_key' => $key, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** @return array<string, string> */
    public function routes(): array
    {
        $saved = DB::table('verification_routes')->pluck('provider_key', 'check_type')->all();

        return collect(CheckType::cases())->mapWithKeys(fn (CheckType $c) => [$c->value => (string) ($saved[$c->value] ?? self::MANUAL)])->all();
    }

    /** @return array<string, string> */
    public function credentials(string $key): array
    {
        return (array) ($this->setting($key)->credentials ?? []);
    }
}
