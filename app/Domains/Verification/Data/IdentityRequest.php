<?php

namespace App\Domains\Verification\Data;

/** What we send to a provider for an identity check: no more than the check needs. */
final readonly class IdentityRequest
{
    public function __construct(
        public string $reference,      // our verification ULID, used as the provider's job/user id
        public string $firstName,
        public string $lastName,
        public string $idNumber,
        public ?string $dateOfBirth = null,
        public string $country = 'ZA',
        public string $documentType = 'sa_id',
    ) {}
}
