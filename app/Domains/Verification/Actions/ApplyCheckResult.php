<?php

namespace App\Domains\Verification\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Verification\Data\CheckResult;
use App\Domains\Verification\Enums\Outcome;
use App\Domains\Verification\Models\Verification;
use Illuminate\Support\Carbon;

/**
 * Records a provider result on a verification and moves its status:
 * any failure → rejected; anything needing review → review queue;
 * all checks passed → verified (decision K4).
 */
class ApplyCheckResult
{
    public function handle(Verification $verification, string $provider, CheckResult $result, string $check = 'identity'): void
    {
        $checks = (array) ($verification->checks ?? []);
        $checks[$check] = ['provider' => $provider] + $result->toArray() + ['at' => now()->toIso8601String()];
        $verification->checks = $checks;

        // Only a decision made by a staff member stands; automatic outcomes are
        // always recalculated from every check, so one passing check can never
        // verify a buyer while another still needs review.
        if ($verification->getAttribute('reviewed_by') !== null && in_array($verification->status, ['verified', 'rejected'], true)) {
            $verification->save();

            return;
        }

        $outcomes = array_column($checks, 'outcome');
        $verification->status = match (true) {
            in_array(Outcome::Failed->value, $outcomes, true) => 'rejected',
            in_array(Outcome::Review->value, $outcomes, true) => 'review',
            default => 'verified',
        };
        if ($verification->status === 'verified') {
            $verification->verified_at = Carbon::now();
            $verification->expires_at = Carbon::now()->addMonths(24);
        }
        if ($verification->status === 'rejected') {
            $verification->rejection_reason = implode(' ', $result->reasons);
        }
        $verification->save();

        Audit::record('kyc', "check_{$result->outcome->value}", "Verification {$verification->ulid}: {$check} check {$result->outcome->value} ({$provider})", $verification, null, [
            'reasons' => $result->reasons, 'reference' => $result->reference,
        ]);
    }
}
