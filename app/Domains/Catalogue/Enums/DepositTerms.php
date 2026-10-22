<?php

namespace App\Domains\Catalogue\Enums;

/**
 * How a bidder's deposit is treated (sprint 3 spec, revision 2).
 */
enum DepositTerms: string
{
    /** Held separately, refunded in full; never applied to the price or forfeited automatically. */
    case Security = 'security';

    /** Winner's deposit counts toward the price; forfeiture only under the sale terms. */
    case Applied = 'applied';

    public function label(): string
    {
        return match ($this) {
            self::Security => 'Refundable security deposit',
            self::Applied => 'Applied to the purchase price',
        };
    }
}
