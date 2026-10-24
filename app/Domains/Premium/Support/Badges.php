<?php

namespace App\Domains\Premium\Support;

use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Premium\Models\PremiumSlot;
use App\Domains\Settings\Settings\AuctionSettings;

/** PRD badges, computed (never stored): Premium, New, Hot, Renovated. */
final class Badges
{
    /** @return list<string> */
    public static function for(Listing $listing): array
    {
        $s = app(AuctionSettings::class);
        $badges = [];

        if (PremiumSlot::active()->where('listing_id', $listing->id)->exists()) {
            $badges[] = 'premium';
        }
        if ($listing->status === ListingStatus::Live && $listing->starts_at !== null && $listing->starts_at->gte(now()->subDays($s->new_badge_days))) {
            $badges[] = 'new';
        }
        if ($listing->status === ListingStatus::Live && (int) AuctionState::where('listing_id', $listing->id)->value('bid_count') >= $s->hot_badge_min_bids) {
            $badges[] = 'hot';
        }
        if ($listing->deal_type->value === 'renovated') {
            $badges[] = 'renovated';
        }

        return $badges;
    }
}
