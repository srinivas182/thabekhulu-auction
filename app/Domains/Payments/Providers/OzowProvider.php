<?php

namespace App\Domains\Payments\Providers;

use App\Domains\Payments\Contracts\PaymentProvider;
use App\Domains\Payments\Data\ConfirmedPayment;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Data\CheckResult;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Ozow instant EFT. Its request and notification hashes depend on an exact
 * field order that must be confirmed against sandbox credentials, so this
 * adapter is "not ready" and cannot be enabled until completed. (Ozow is also
 * available inside TradeSafe's payment page for escrowed deposits.)
 */
final class OzowProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'ozow';
    }

    public function name(): string
    {
        return 'Ozow (instant EFT)';
    }

    public function ready(): bool
    {
        return false;
    }

    public function integrationNote(): string
    {
        return 'Available once Ozow provides sandbox credentials; the developer completes the connection. Escrowed deposits can already be paid by Ozow inside TradeSafe’s payment page.';
    }

    public function supports(): array
    {
        return ['small'];
    }

    public function credentialFields(): array
    {
        return [
            ['name' => 'site_code', 'label' => 'Site code', 'secret' => false],
            ['name' => 'private_key', 'label' => 'Private key', 'secret' => true],
            ['name' => 'api_key', 'label' => 'API key', 'secret' => true],
        ];
    }

    public function testConnection(array $credentials, string $mode): CheckResult
    {
        return CheckResult::review('Ozow connection is not completed yet.');
    }

    public function checkoutUrl(Deposit $deposit, array $credentials, string $mode, string $returnUrl): string
    {
        throw new RuntimeException('Ozow is not available yet.');
    }

    public function confirm(Request $request, array $credentials, string $mode): ?ConfirmedPayment
    {
        return null;
    }
}
