<?php

namespace App\Domains\Premium\Support;

use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Settlement\Models\SettlementCase;
use App\Domains\Verification\Models\BidderApproval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The public result of a sold auction (PRD): price, date and bidder number,
 * never the buyer's name. If the winner defaulted and the runner-up bought,
 * the runner-up's price and number are shown.
 */
final class SoldResult
{
    /** @return array{price: int, date: string, bidderNumber: string|null}|null */
    public static function for(Listing $listing): ?array
    {
        if ($listing->status !== ListingStatus::Sold) {
            return null; // the price is published once settlement completes, not while under offer
        }
        $r = DB::table('auction_results')->where('listing_id', $listing->id)->where('outcome', 'sold')->first();
        if ($r === null) {
            return null;
        }
        $runnerUp = SettlementCase::where('listing_id', $listing->id)->where('kind', 'runner_up')->whereIn('status', ['open', 'completed'])->first();
        $approvalId = $runnerUp->bidder_approval_id ?? (int) $r->winner_approval_id;

        return [
            'price' => $runnerUp->price ?? (int) $r->winning_amount,
            'date' => CarbonImmutable::parse((string) $r->closed_at)->setTimezone((string) config('platform.display_timezone'))->format('j F Y'),
            'bidderNumber' => BidderApproval::whereKey($approvalId)->value('bidder_number'),
        ];
    }
}
