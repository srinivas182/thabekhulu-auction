<?php

namespace App\Domains\Bidding\Support;

use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Http\Controllers\BidController;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;

/**
 * The live auction as the public and the signed-in buyer see it. Only bidder
 * numbers, amounts and times; never names (B7). Times pre-formatted in SAST
 * on the server so server and browser render the same text.
 */
final class AuctionView
{
    /** @return array<string, mixed>|null */
    public static function for(Listing $listing, ?User $user): ?array
    {
        if ($listing->auction_type->value !== 'open' || ! $listing->status->isPublic()) {
            return null;
        }

        $state = BidEngine::state($listing);
        $tz = (string) config('platform.display_timezone');
        $recent = Bid::where('listing_id', $listing->id)->whereNull('voided_at')->orderByDesc('id')->limit(10)->get();

        $mine = null;
        if ($user !== null && $user->isBuyer()) {
            $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->where('status', 'approved')->first();
            if ($approval !== null) {
                $mine = [
                    'bidderNumber' => $approval->bidder_number,
                    'canBid' => Deposit::where('bidder_approval_id', $approval->id)->where('status', 'received')->exists() || (int) $listing->deposit_amount === 0,
                    'leading' => $state->leader_approval_id === $approval->id,
                    'hasBid' => Bid::where('listing_id', $listing->id)->where('bidder_approval_id', $approval->id)->whereNull('voided_at')->exists(),
                ];
            }
        }

        return [
            'current' => $state->current_amount,
            'next' => Increments::next($state->current_amount, (int) $listing->minimum_price),
            'endsAt' => $state->ends_at->toIso8601String(),
            'endsAtText' => $state->ends_at->setTimezone($tz)->format('j F Y, H:i').' SAST',
            'extensions' => $state->extensions,
            'bidCount' => $state->bid_count,
            'paused' => $state->paused_at !== null,
            'closed' => $state->ends_at->isPast(),
            'otpFrom' => BidController::otpThreshold($listing),
            'recent' => $recent->map(fn (Bid $b) => ['number' => $b->bidder_number, 'amount' => $b->amount, 'kind' => $b->kind, 'time' => $b->placed_at->setTimezone($tz)->format('H:i:s')])->values(),
            'mine' => $mine,
        ];
    }
}
