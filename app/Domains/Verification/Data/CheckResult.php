<?php

namespace App\Domains\Verification\Data;

use App\Domains\Verification\Enums\Outcome;

/**
 * A normalised provider result. Only the provider's reference, the outcome
 * and short reasons are kept; raw responses are never stored (decision K3).
 */
final readonly class CheckResult
{
    /** @param list<string> $reasons */
    public function __construct(
        public Outcome $outcome,
        public array $reasons = [],
        public ?string $reference = null,
        public ?float $score = null,
    ) {}

    public static function review(string $reason, ?string $reference = null): self
    {
        return new self(Outcome::Review, [$reason], $reference);
    }

    /** @return array{outcome: string, reasons: list<string>, reference: string|null, score: float|null} */
    public function toArray(): array
    {
        return ['outcome' => $this->outcome->value, 'reasons' => $this->reasons, 'reference' => $this->reference, 'score' => $this->score];
    }
}
