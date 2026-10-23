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
 * Smile ID (docs.usesmileid.com). Requests are signed with
 * base64(HMAC-SHA256(api_key, timestamp + partner_id + "sid_request")),
 * and callbacks carry a signature checked the same way.
 * ID numbers are checked against South African government records (Enhanced KYC).
 */
final class SmileIdProvider implements VerificationProvider
{
    private const BASE = ['sandbox' => 'https://testapi.smileidentity.com', 'live' => 'https://api.smileidentity.com'];

    public function key(): string
    {
        return 'smile_id';
    }

    public function name(): string
    {
        return 'Smile ID';
    }

    public function supports(): array
    {
        return [CheckType::SaIdentity, CheckType::PassportIdentity, CheckType::Liveness, CheckType::Company, CheckType::Screening, CheckType::BankAccount];
    }

    public function credentialFields(): array
    {
        return [
            ['name' => 'partner_id', 'label' => 'Partner ID', 'secret' => false],
            ['name' => 'api_key', 'label' => 'API key for signature', 'secret' => true],
        ];
    }

    public function ready(): bool
    {
        return true;
    }

    public function integrationNote(): string
    {
        return 'SA ID checks against government records are built in. Confirm result codes in the sandbox before switching to live.';
    }

    /** @return array{timestamp: string, signature: string} */
    public static function sign(string $partnerId, string $apiKey, ?string $timestamp = null): array
    {
        $timestamp ??= now()->toIso8601ZuluString('millisecond');

        return ['timestamp' => $timestamp, 'signature' => base64_encode(hash_hmac('sha256', $timestamp.$partnerId.'sid_request', $apiKey, true))];
    }

    public function testConnection(array $credentials, string $mode): CheckResult
    {
        // A signed job-status query for a job that doesn't exist: bad credentials are refused, good ones get "not found".
        $response = $this->post('/v1/job_status', $credentials, $mode, [
            'user_id' => 'connection-test', 'job_id' => 'connection-test', 'image_links' => false, 'history' => false,
        ]);

        if ($response === null) {
            return new CheckResult(Outcome::Failed, ['Smile ID could not be reached.']);
        }

        return in_array($response->status(), [401, 403], true)
            ? new CheckResult(Outcome::Failed, ['Smile ID refused the credentials.'])
            : new CheckResult(Outcome::Passed, ['Connected to Smile ID ('.$mode.').']);
    }

    public function checkIdentity(IdentityRequest $request, array $credentials, string $mode): CheckResult
    {
        $response = $this->post('/v1/id_verification', $credentials, $mode, [
            'country' => $request->country,
            'id_type' => $request->documentType === 'passport' ? 'PASSPORT' : 'NATIONAL_ID',
            'id_number' => $request->idNumber,
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'dob' => $request->dateOfBirth,
            'partner_params' => ['job_id' => $request->reference, 'user_id' => $request->reference, 'job_type' => 5],
            'source_sdk' => 'rest_api',
        ]);

        if ($response === null || ! $response->successful()) {
            return CheckResult::review('Smile ID was unavailable; check queued for manual review.');
        }

        return $this->interpret((array) $response->json());
    }

    public function verifyWebhook(Request $request, array $credentials): bool
    {
        $signature = (string) $request->input('signature');
        $timestamp = (string) $request->input('timestamp');

        if ($signature === '' || $timestamp === '' || ! isset($credentials['partner_id'], $credentials['api_key'])) {
            return false;
        }

        return hash_equals(self::sign($credentials['partner_id'], $credentials['api_key'], $timestamp)['signature'], $signature);
    }

    public function parseWebhook(Request $request): array
    {
        $body = (array) $request->all();

        return [
            'event_id' => (string) ($body['SmileJobID'] ?? $body['timestamp'] ?? ''),
            'reference' => isset($body['PartnerParams']['job_id']) ? (string) $body['PartnerParams']['job_id'] : null,
            'result' => $this->interpret($body),
            'type' => 'job_result',
        ];
    }

    /**
     * Decides on the explicit "Actions" fields; anything not clearly verified goes to review.
     *
     * @param  array<string, mixed>  $body
     */
    private function interpret(array $body): CheckResult
    {
        $actions = (array) ($body['Actions'] ?? []);
        $idCheck = (string) ($actions['Verify_ID_Number'] ?? '');
        $names = (string) ($actions['Names'] ?? '');
        $reference = isset($body['SmileJobID']) ? (string) $body['SmileJobID'] : null;
        $text = (string) ($body['ResultText'] ?? '');

        if ($idCheck === 'Verified' && $names === 'Exact Match') {
            return new CheckResult(Outcome::Passed, ['ID number verified and names match.'], $reference);
        }
        if ($idCheck === 'Not Verified') {
            return new CheckResult(Outcome::Failed, ['ID number could not be verified.'.($text ? " ({$text})" : '')], $reference);
        }
        if ($idCheck === 'Verified') {
            return CheckResult::review("ID number verified but names are a {$names}.", $reference);
        }

        return CheckResult::review('Result needs review'.($text ? ": {$text}" : '.'), $reference);
    }

    /**
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $credentials, string $mode, array $payload): ?Response
    {
        $signed = self::sign((string) ($credentials['partner_id'] ?? ''), (string) ($credentials['api_key'] ?? ''));

        try {
            return Http::timeout(20)->acceptJson()->post(self::BASE[$mode === 'live' ? 'live' : 'sandbox'].$path, $payload + [
                'partner_id' => (string) ($credentials['partner_id'] ?? ''),
            ] + $signed);
        } catch (Throwable) {
            return null;
        }
    }
}
