<?php

namespace App\Domains\Bidding\Support;

use App\Domains\Settings\Settings\AuctionSettings;

/** Increment bands (B2): the step that applies at a given current bid. */
final class Increments
{
    public static function at(int $amount): int
    {
        foreach (app(AuctionSettings::class)->increment_bands as $band) {
            [$upTo, $step] = [$band[0] ?? null, (int) ($band[1] ?? 0)];
            if ($upTo === null || $amount < (int) $upTo) {
                return max(1, $step);
            }
        }

        return 1;
    }

    /** The lowest acceptable next bid. */
    public static function next(?int $current, int $minimum): int
    {
        return $current === null ? $minimum : $current + self::at($current);
    }
}
