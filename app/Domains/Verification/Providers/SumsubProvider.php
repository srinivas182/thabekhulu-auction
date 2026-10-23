<?php

namespace App\Domains\Verification\Providers;

use App\Domains\Verification\Contracts\VerificationProvider;
use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Data\IdentityRequest;
use App\Domains\Verification\Enums\CheckType;
use App\Domains\Verification\Enums\Outcome;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sumsub (docs.sumsub.com). Requests carry X-App-Token, X-App-Access-Ts
 * (seconds) and X-App-Access-Sig = lowercase hex HMAC-SHA256(secret,
 * ts + METHOD + path?query + body). The applicant completes document and
 * liveness checks in Sumsub's hosted flow; the decision arrives by webhook.
 */
final class SumsubProvider implements VerificationProvider
{
    private const BASE = 'https://api.sumsub.com'; // same host for sandbox and live; the token decides

    public function key(): string
    {
        return 'sumsub';
    }

    public function name(): string
    {
        return 'Sumsub';
    }

    public function supports(): array
    {
        return [CheckType::PassportIdentity, CheckType::SaIdentity, CheckType::Liveness, CheckType::Company, CheckType::Screening, CheckType::BankAccount];
    }

    public function credentialFields(): array
    {
        return [
            ['name' => 'app_token', 'label' => 'App token', 'secret' => true],
            ['name' => 'secret_key', 'label' => 'Secret key', 'secret' => true],
            ['name' => 'level_name', 'label' => 'Verification level name', 'secret' => false],
            ['name' => 'webhook_secret', 'label' => 'Webhook secret key', 'secret' => true],
        ];
    }

    public function ready(): bool
    {
        return true;
    }

    public function integrationNote(): string
    {
        return 'Best for passports and foreign investors. Set up the verification level (document + liveness) in the Sumsub dashboard and point its webhook at this platform.';
    }

    /** @return array<string, string> */
    public static function headers(string $token, string $secret, string $method, string $pathWithQuery, string $body = '', ?int $ts = null): array
    {
        $ts ??= now()->getTimestamp();

        return [
            'X-App-Token' => $token,
            'X-App-Access-Ts' => (string) $ts,
            'X-App-Access-Sig' => hash_hmac('sha256', $ts.strtoupper($method).$pathWithQuery.$body, $secret),
        ];
    }

    public function testConnection(array $credentials, string $mode): CheckResult
    {
        // Looking up an applicant that doesn't exist: bad signatures get 401, good ones 404.
        $response = $this->send('GET', '/resources/applicants/-;externalUserId=connection-test/one', $credentials);

        if ($response === null) {
            return new CheckResult(Outcome::Failed, ['Sumsub could not be reached.']);
        }

        return $response->status() === 401
            ? new CheckResult(Outcome::Failed, ['Sumsub refused the app token or signature.'])
            : new CheckResult(Outcome::Passed, ['Connected to Sumsub.']);
    }

    public function checkIdentity(IdentityRequest $request, array $credentials, string $mode): CheckResult
    {
        $level = rawurlencode((string) ($credentials['level_name'] ?? 'basic-kyc-level'));
        $body = (string) json_encode([
            'externalUserId' => $request->reference,
            'info' => array_filter([
                'firstName' => $request->firstName, 'lastName' => $request->lastName, 'dob' => $request->dateOfBirth,
                'country' => $request->country === 'ZA' ? 'ZAF' : $request->country,
            ]),
        ]);

        $response = $this->send('POST', "/resources/applicants?levelName={$level}", $credentials, $body);

        if ($response === null || ! $response->successful()) {
            return CheckResult::review('Sumsub was unavailable; check queued for manual review.');
        }

        // The decision arrives later by webhook (applicantReviewed).
        return CheckResult::review('Waiting for the buyer to complete Sumsub’s document and selfie check.', (string) $response->json('id'));
    }

    /**
     * Access token for Sumsub's hosted web flow (document + liveness).
     *
     * @param  array<string, string>  $credentials
     */
    public function accessToken(string $reference, array $credentials): ?string
    {
        $body = (string) json_encode(['userId' => $reference, 'levelName' => (string) ($credentials['level_name'] ?? 'basic-kyc-level'), 'ttlInSecs' => 1800]);
        $response = $this->send('POST', '/resources/accessTokens/sdk', $credentials, $body);

        return $response?->successful() ? (string) $response->json('token') : null;
    }

    public function verifyWebhook(Request $request, array $credentials): bool
    {
        $digest = (string) $request->header('X-Payload-Digest');
        $secret = (string) ($credentials['webhook_secret'] ?? '');

        return $digest !== '' && $secret !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), strtolower($digest));
    }

    public function parseWebhook(Request $request): array
    {
        $body = (array) $request->json()->all();
        $answer = (string) data_get($body, 'reviewResult.reviewAnswer', '');
        $reject = (string) data_get($body, 'reviewResult.reviewRejectType', '');

        $result = match (true) {
            ($body['type'] ?? null) !== 'applicantReviewed' => null,
            $answer === 'GREEN' => new CheckResult(Outcome::Passed, ['Document and selfie verified by Sumsub.'], (string) ($body['applicantId'] ?? '')),
            $answer === 'RED' && $reject === 'FINAL' => new CheckResult(Outcome::Failed, ['Sumsub rejected the verification.'], (string) ($body['applicantId'] ?? '')),
            default => CheckResult::review('Sumsub asked for the verification to be retried or reviewed.', (string) ($body['applicantId'] ?? '')),
        };

        return [
            'event_id' => implode(':', [(string) ($body['applicantId'] ?? ''), (string) ($body['type'] ?? ''), (string) ($body['createdAtMs'] ?? '')]),
            'reference' => isset($body['externalUserId']) ? (string) $body['externalUserId'] : null,
            'result' => $result,
            'type' => isset($body['type']) ? (string) $body['type'] : null,
        ];
    }

    /** @param array<string, string> $credentials */
    private function send(string $method, string $pathWithQuery, array $credentials, string $body = ''): ?Response
    {
        $headers = self::headers((string) ($credentials['app_token'] ?? ''), (string) ($credentials['secret_key'] ?? ''), $method, $pathWithQuery, $body);

        try {
            $request = Http::timeout(20)->acceptJson()->withHeaders($headers);

            return $method === 'GET'
                ? $request->get(self::BASE.$pathWithQuery)
                : $request->withBody($body, 'application/json')->send($method, self::BASE.$pathWithQuery);
        } catch (Throwable) {
            return null;
        }
    }
}
