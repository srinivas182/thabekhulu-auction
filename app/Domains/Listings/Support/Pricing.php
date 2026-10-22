<?php

namespace App\Domains\Listings\Support;

use App\Domains\Listings\Enums\DealType;
use App\Domains\Listings\Models\Listing;

/**
 * The agreed pricing rules (PRD 4.1, sprint 3 spec 5.2).
 *
 * Commission sale: seller receives winning bid − fee; the minimum must be
 * at least seller net ÷ (1 − fee). Renovated: seller receives the fixed
 * seller price; the minimum must be at least seller price + renovation.
 */
final class Pricing
{
    /** Lowest minimum selling price allowed; null until seller terms are entered. */
    public static function floor(DealType $deal, ?int $sellerNet, ?int $sellerPrice, int $renovation, int $feeBps): ?int
    {
        if ($deal === DealType::Commission) {
            if ($sellerNet === null || $feeBps >= 10000) {
                return null;
            }

            // Round up to the next rand so the seller always nets their price.
            return (int) ceil($sellerNet * 10000 / (10000 - $feeBps));
        }

        return $sellerPrice === null ? null : $sellerPrice + $renovation;
    }

    public static function floorFor(Listing $listing): ?int
    {
        return self::floor($listing->deal_type, $listing->seller_net_price, $listing->seller_price, $listing->renovation_cost, $listing->fee_bps);
    }

    /**
     * What each party receives at a given winning bid.
     *
     * @return array{bid: int, fee: int, seller: int, thabekhulu: int}
     */
    public static function payout(Listing $listing, int $bid): array
    {
        if ($listing->deal_type === DealType::Commission) {
            $fee = (int) round($bid * $listing->fee_bps / 10000);

            return ['bid' => $bid, 'fee' => $fee, 'seller' => $bid - $fee, 'thabekhulu' => $fee];
        }

        $seller = (int) $listing->seller_price;

        return ['bid' => $bid, 'fee' => 0, 'seller' => $seller, 'thabekhulu' => $bid - $seller];
    }

    /**
     * Deposit as a share of the minimum, and whether it is above the 10%
     * forfeiture cap (CPA Regulation 24) that triggers a warning.
     *
     * @return array{percent: float|null, aboveCap: bool}
     */
    public static function depositCheck(int $deposit, ?int $minimum): array
    {
        if ($minimum === null || $minimum === 0) {
            return ['percent' => null, 'aboveCap' => false];
        }

        $percent = round($deposit / $minimum * 100, 1);

        return ['percent' => $percent, 'aboveCap' => $percent > 10];
    }
}
