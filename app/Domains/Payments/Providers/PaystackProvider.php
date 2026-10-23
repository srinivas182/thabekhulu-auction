<?php

namespace App\Domains\Payments\Providers;

use App\Domains\Payments\Contracts\PaymentProvider;
use App\Domains\Payments\Data\ConfirmedPayment;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Enums\Outcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Paystack (paystack.com/docs): Bearer secret key; transaction/initialize
 * returns a hosted checkout; webhooks carry x-paystack-signature =
 * HMAC-SHA512(body, secret key). Every charge is re-verified with
 * transaction/verify before money is recorded. For small card payments.
 */
final class PaystackProvider implements PaymentProvider
{
    private const BASE = 'https://api.paystack.co';

    public function key(): string
    {
        return 'paystack';
    }

    public function name(): string
    {
        return 'Paystack (cards)';
    }

    public function ready(): bool
    {
        return true;
    }

    public function integrationNote(): string
    {
        return 'Card payments for smaller amounts (tickets, fees). Use the test secret key in sandbox and the live key in live mode; set the webhook URL shown below in the Paystack dashboard.';
    }

    public function supports(): array
    {
        return ['small'];
    }

    public function credentialFields(): array
    {
        return [['name' => 'secret_key', 'label' => 'Secret key', 'secret' => true]];
    }

    public function testConnection(array $credentials, string $mode): CheckResult
    {
        try {
            $r = Http::timeout(15)->withToken((string) ($credentials['secret_key'] ?? ''))->get(self::BASE.'/transaction', ['perPage' => 1]);
        } catch (Throwable) {
            return new CheckResult(Outcome::Failed, ['Paystack could not be reached.']);
        }

        return $r->successful()
            ? new CheckResult(Outcome::Passed, ['Connected to Paystack.'])
            : new CheckResult(Outcome::Failed, ['Paystack refused the secret key.']);
    }

    public function checkoutUrl(Deposit $deposit, array $credentials, string $mode, string $returnUrl): string
    {
        $deposit->loadMissing('user');
        $attempt = $deposit->reference.'-'.now()->format('His');

        $r = Http::timeout(20)->withToken((string) ($credentials['secret_key'] ?? ''))->post(self::BASE.'/transaction/initialize', [
            'email' => $deposit->user->email, 'amount' => $deposit->amount - $deposit->balance(), 'currency' => 'ZAR',
            'reference' => $attempt, 'callback_url' => $returnUrl, 'metadata' => ['deposit_reference' => $deposit->reference],
        ]);
        $url = $r->json('data.authorization_url');
        if (! $r->successful() || ! is_string($url)) {
            throw new RuntimeException('Paystack did not start the payment.');
        }
        $deposit->provider_ref = $attempt;
        $deposit->method = 'paystack';

        return $url;
    }

    public function confirm(Request $request, array $credentials, string $mode): ?ConfirmedPayment
    {
        $secret = (string) ($credentials['secret_key'] ?? '');
        $signature = (string) $request->header('x-paystack-signature');
        if ($secret === '' || $signature === '' || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) {
            return null;
        }
        if ($request->input('event') !== 'charge.success') {
            return null;
        }

        $reference = (string) $request->input('data.reference');
        $v = Http::timeout(20)->withToken($secret)->get(self::BASE.'/transaction/verify/'.rawurlencode($reference));
        if (! $v->successful() || $v->json('data.status') !== 'success' || $v->json('data.currency') !== 'ZAR') {
            return null;
        }

        return new ConfirmedPayment((string) $v->json('data.metadata.deposit_reference'), (int) $v->json('data.amount'), $reference, 'charge:'.$reference);
    }
}
