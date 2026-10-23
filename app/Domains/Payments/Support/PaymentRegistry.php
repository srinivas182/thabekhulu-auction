<?php

namespace App\Domains\Payments\Support;

use App\Domains\Payments\Contracts\PaymentProvider;
use App\Domains\Payments\Models\PaymentProviderSetting;
use App\Domains\Payments\Providers\OzowProvider;
use App\Domains\Payments\Providers\PaystackProvider;
use App\Domains\Payments\Providers\TradeSafeProvider;
use App\Domains\Payments\Settings\PaymentSettings;

/** Built-in payment providers and their settings; trust-account EFT is always available. */
final class PaymentRegistry
{
    /** @return array<string, PaymentProvider> */
    public function all(): array
    {
        $list = [app(TradeSafeProvider::class), app(OzowProvider::class), app(PaystackProvider::class)];

        return array_combine(array_map(fn (PaymentProvider $p) => $p->key(), $list), $list);
    }

    public function find(string $key): ?PaymentProvider
    {
        return $this->all()[$key] ?? null;
    }

    public function setting(string $key): PaymentProviderSetting
    {
        return PaymentProviderSetting::firstOrCreate(['key' => $key], ['enabled' => false, 'mode' => 'sandbox']);
    }

    public function usable(string $key, string $purpose): bool
    {
        $p = $this->find($key);
        $s = PaymentProviderSetting::where('key', $key)->first();

        return $p !== null && $p->ready() && in_array($purpose, $p->supports(), true) && $s !== null && $s->enabled && ! empty($s->credentials);
    }

    /** The escrow provider offered for deposits, if enabled; otherwise null (EFT only). */
    public function forDeposits(): ?PaymentProvider
    {
        $key = app(PaymentSettings::class)->deposit_provider;

        return $key !== 'none' && $this->usable($key, 'deposit') ? $this->find($key) : null;
    }

    /** @return array<string, string> */
    public function credentials(string $key): array
    {
        return (array) ($this->setting($key)->credentials ?? []);
    }
}
