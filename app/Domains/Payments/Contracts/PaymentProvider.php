<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Data\ConfirmedPayment;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Data\CheckResult;
use Illuminate\Http\Request;

/**
 * One payment provider (decision P1). Credentials are passed in on each
 * call, decrypted from the database.
 */
interface PaymentProvider
{
    public function key(): string;

    public function name(): string;

    /** False until the integration is confirmed; not-ready providers can't be enabled. */
    public function ready(): bool;

    public function integrationNote(): string;

    /** "deposit" (escrow for large deposits) and/or "small" (tickets, fees). */
    /** @return list<string> */
    public function supports(): array;

    /** @return list<array{name: string, label: string, secret: bool}> */
    public function credentialFields(): array;

    /** @param array<string, string> $credentials */
    public function testConnection(array $credentials, string $mode): CheckResult;

    /**
     * Prepares the payment and returns the provider's checkout URL; may set
     * $deposit->provider_ref (the caller saves it).
     *
     * @param  array<string, string>  $credentials
     */
    public function checkoutUrl(Deposit $deposit, array $credentials, string $mode, string $returnUrl): string;

    /**
     * Authenticates a callback and asks the provider for the real state.
     * Returns a payment only when the provider confirms funds; null otherwise.
     *
     * @param  array<string, string>  $credentials
     */
    public function confirm(Request $request, array $credentials, string $mode): ?ConfirmedPayment;
}
