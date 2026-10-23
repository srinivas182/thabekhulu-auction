<?php

namespace App\Domains\Verification\Providers;

use App\Domains\Verification\Contracts\VerificationProvider;
use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Data\IdentityRequest;
use App\Domains\Verification\Enums\CheckType;
use Illuminate\Http\Request;

/**
 * VerifyID / OmniCheck (api.verifyid.co.za). Its published endpoints cover
 * SA ID validation, live Home Affairs checks (with an offline fallback),
 * AML/PEP screening, bank and CIPC checks. Its authentication scheme is
 * shared with registered clients only, so this adapter is "not ready" and
 * cannot be routed until it is completed against sandbox credentials.
 */
final class VerifyIdProvider implements VerificationProvider
{
    public function key(): string
    {
        return 'verifyid';
    }

    public function name(): string
    {
        return 'VerifyID (OmniCheck)';
    }

    public function supports(): array
    {
        return [CheckType::SaIdentity, CheckType::Screening, CheckType::BankAccount, CheckType::Company];
    }

    public function credentialFields(): array
    {
        return [['name' => 'api_key', 'label' => 'API key', 'secret' => true]];
    }

    public function ready(): bool
    {
        return false;
    }

    public function integrationNote(): string
    {
        return 'Available once VerifyID provides sandbox credentials and API documentation; the developer completes the connection, then it can be selected.';
    }

    public function testConnection(array $credentials, string $mode): CheckResult
    {
        return CheckResult::review('VerifyID connection is not completed yet.');
    }

    public function checkIdentity(IdentityRequest $request, array $credentials, string $mode): CheckResult
    {
        return CheckResult::review('VerifyID connection is not completed yet; manual review.');
    }

    public function verifyWebhook(Request $request, array $credentials): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return ['event_id' => '', 'reference' => null, 'result' => null, 'type' => null];
    }
}
