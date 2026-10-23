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

    public static function group(): string
    {
        return 'auction';
    }
}
