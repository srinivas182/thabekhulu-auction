<?php

namespace App\Domains\Verification\Http\Controllers;

use App\Domains\Verification\Actions\ApplyCheckResult;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Support\ProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provider callbacks (decision K5): signature verified, each event processed
 * once, result applied to the matching verification.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, ProviderRegistry $registry, ApplyCheckResult $apply): JsonResponse
    {
        $adapter = $registry->find($provider) ?? abort(404);

        if (! $adapter->verifyWebhook($request, $registry->credentials($provider))) {
            Log::warning('Rejected verification webhook with an invalid signature', ['provider' => $provider, 'ip' => $request->ip()]);

            return response()->json(['ok' => false], 401);
        }

        $event = $adapter->parseWebhook($request);
        if ($event['event_id'] === '') {
            return response()->json(['ok' => true]);
        }

        $verification = $event['reference'] ? Verification::where('ulid', $event['reference'])->first() : null;

        try {
            DB::table('verification_events')->insert([
                'provider_key' => $provider, 'event_id' => $event['event_id'], 'verification_id' => $verification?->id,
                'type' => $event['type'], 'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]); // already processed
        }

        if ($verification !== null && $event['result'] !== null) {
            $apply->handle($verification, $provider, $event['result']);
        }

        return response()->json(['ok' => true]);
    }
}
