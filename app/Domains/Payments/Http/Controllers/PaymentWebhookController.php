<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Payments\Actions\DepositService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Support\PaymentRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provider callbacks. The adapter authenticates the callback and asks the
 * provider for the real state; only a provider-confirmed payment for a
 * deposit whose provider id matches is recorded, exactly once.
 */
class PaymentWebhookController extends Controller
{
    /** TradeSafe callbacks carry their secret in the URL ({secret}); it is checked by the adapter. */
    public function tradesafe(Request $request, PaymentRegistry $registry, DepositService $deposits, string $secret): JsonResponse
    {
        return $this->handle($request, $registry, $deposits, 'tradesafe');
    }

    public function __invoke(Request $request, PaymentRegistry $registry, DepositService $deposits, string $provider): JsonResponse
    {
        abort_if($provider === 'tradesafe', 404); // TradeSafe must use its secret URL

        return $this->handle($request, $registry, $deposits, $provider);
    }

    private function handle(Request $request, PaymentRegistry $registry, DepositService $deposits, string $provider): JsonResponse
    {
        $adapter = $registry->find($provider) ?? abort(404);
        $setting = $registry->setting($provider);
        if (! $setting->enabled) {
            return response()->json(['ok' => false], 404);
        }

        $payment = $adapter->confirm($request, $registry->credentials($provider), $setting->mode);
        if ($payment === null) {
            return response()->json(['ok' => true]); // nothing confirmed; no detail for callers
        }

        $deposit = Deposit::where('reference', $payment->reference)->first();
        if ($deposit === null || ($deposit->provider_ref !== null && $provider === 'tradesafe' && $deposit->provider_ref !== $payment->providerRef)) {
            Log::warning('Confirmed payment does not match a deposit', ['provider' => $provider, 'reference' => $payment->reference]);

            return response()->json(['ok' => true]);
        }

        try {
            DB::table('payment_events')->insert(['provider_key' => $provider, 'event_id' => $payment->eventId, 'deposit_id' => $deposit->id, 'type' => 'payment', 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $deposits->receive($deposit, $payment->amountCents, 'provider', "{$provider}:{$payment->providerRef}", null, $adapter->name());

        return response()->json(['ok' => true]);
    }
}
