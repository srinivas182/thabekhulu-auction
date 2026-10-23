<?php

namespace App\Domains\Payments\Data;

/**
 * A payment the provider itself has confirmed (never taken from a callback
 * body alone): which deposit, how much, and the provider's ids.
 */
final readonly class ConfirmedPayment
{
    public function __construct(
        public string $reference,     // our deposit reference
        public int $amountCents,
        public string $providerRef,   // provider transaction id
        public string $eventId,       // for exactly-once processing
    ) {}
}
