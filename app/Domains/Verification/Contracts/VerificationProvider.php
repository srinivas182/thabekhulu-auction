<?php

namespace App\Domains\Verification\Contracts;

use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Data\IdentityRequest;
use App\Domains\Verification\Enums\CheckType;
use Illuminate\Http\Request;

/**
 * One verification provider (decision K1). Credentials are passed in on each
 * call, decrypted from the database; adapters never read them from config.
 */
interface VerificationProvider
{
    public function key(): string;

    public function name(): string;

    /** @return list<CheckType> */
    public function supports(): array;

    /**
     * Fields the super admin fills in. "secret" fields are never shown again.
     *
     * @return list<array{name: string, label: string, secret: bool}>
     */
    public function credentialFields(): array;

    /** False until the adapter's live integration is confirmed; not-ready providers cannot be routed. */
    public function ready(): bool;

    /** Short note shown on the settings page (e.g. what still needs sandbox confirmation). */
    public function integrationNote(): ?string;

    /** @param array<string, string> $credentials */
    public function testConnection(array $credentials, string $mode): CheckResult;

    /** @param array<string, string> $credentials */
    public function checkIdentity(IdentityRequest $request, array $credentials, string $mode): CheckResult;

    /**
     * Verifies a provider callback's signature (decision K5).
     *
     * @param  array<string, string>  $credentials
     */
    public function verifyWebhook(Request $request, array $credentials): bool;

    /**
     * Extracts the provider's event id, our reference and the normalised result from a verified callback.
     *
     * @return array{event_id: string, reference: string|null, result: CheckResult|null, type: string|null}
     */
    public function parseWebhook(Request $request): array;
}
