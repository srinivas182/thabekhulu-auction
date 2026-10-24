<?php

namespace App\Domains\Settings\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Platform-wide auction defaults. Individual listings can override the
 * duration (Sprint 3); the rest apply to every auction.
 */
class AuctionSettings extends Settings
{
    public int $default_duration_days;

    public bool $soft_close_enabled;

    public int $soft_close_window_minutes;

    public int $soft_close_extension_minutes;

    public int $closing_soon_hours;

    public int $refund_delay_days;

    public int $runner_up_hold_days;

    /** Bids at or above this amount (in rand) need a one-time PIN. */
    public int $step_up_threshold;

    public int $default_suspension_months;

    public int $hot_badge_min_bids;

    public int $new_badge_days;

    /** Rand: proof of funds needed at or above this minimum price (and for all Mining listings). */
    public int $proof_of_funds_threshold;

    /** Business days for the winner to sign the sale agreement (S2). */
    public int $settlement_agreement_business_days;

    /** Days for the balance to be paid or guarantees delivered (S2). */
    public int $settlement_balance_days;

    /** Days the runner-up has to accept an offer after a default (S4). */
    public int $runner_up_accept_days;

    // Increment bands, lowest first: each item is [up to (rand, or null for no limit), increment (rand)].
    // Plain `array` only: spatie/laravel-settings can't parse typed array docblocks (ADR 0008).
    public array $increment_bands; // @phpstan-ignore missingType.iterableValue

    public static function group(): string
    {
        return 'auction';
    }
}
