<?php

namespace App\Domains\Bidding\Support;

use App\Domains\Bidding\Actions\BidEngine;
use App\Domains\Bidding\Actions\TenderService;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Premium\Support\SoldResult;
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
        if (! $listing->status->isShown() || $listing->status === ListingStatus::Scheduled) {
            return null;
        }
        if ($listing->auction_type->value === 'sealed') {
            return self::sealed($listing, $user);
        }

        $state = BidEngine::state($listing);

        $mine = null;
        if ($user !== null && $user->isBuyer()) {
            $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->where('status', 'approved')->first();
            if ($approval !== null) {
                $mine = [
                    'bidderNumber' => $approval->bidder_number,
                    'canBid' => Deposit::where('bidder_approval_id', $approval->id)->where('status', 'received')->exists() || (int) $listing->deposit_amount === 0,
                    'leading' => $state->leader_approval_id === $approval->id,
                    'hasBid' => Bid::where('listing_id', $listing->id)->where('bidder_approval_id', $approval->id)->whereNull('voided_at')->exists(),
                    // Per bidder, never broadcast: from which amount an emailed code is needed (null: none).
                    'codeFrom' => StepUp::requiredFrom($listing, $user), 'codeFirstOnly' => StepUp::mode() === 'first',
                ];
            }
        }

        return self::publicPart($listing, $state) + ['mine' => $mine];
    }

    /**
     * Sealed tender: the deadline and, for the bidder, their own offer only.
     *
     * @return array<string, mixed>
     */
    private static function sealed(Listing $listing, ?User $user): array
    {
        $tz = (string) config('platform.display_timezone');
        $mine = null;
        if ($user !== null && $user->isBuyer()) {
            $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->where('status', 'approved')->first();
            if ($approval !== null) {
                $mine = [
                    'bidderNumber' => $approval->bidder_number,
                    'canBid' => Deposit::where('bidder_approval_id', $approval->id)->where('status', 'received')->exists() || (int) $listing->deposit_amount === 0,
                    'offer' => app(TenderService::class)->ownOffer($listing, $approval->id)['amount'] ?? null,
                ];
            }
        }

        return [
            'kind' => 'sealed', 'endsAt' => $listing->ends_at?->toIso8601String(), 'endsAtText' => $listing->ends_at?->setTimezone($tz)->format('j F Y, H:i').' SAST',
            'closed' => $listing->status !== ListingStatus::Live || ($listing->ends_at?->isPast() ?? true),
            'outcome' => self::outcome($listing), 'result' => SoldResult::for($listing), 'mining' => $listing->category->slug === 'mining', 'otpFrom' => StepUp::requiredFrom($listing, $user), 'minimum' => (int) $listing->minimum_price, 'mine' => $mine,
        ];
    }

    private static function outcome(Listing $listing): ?string
    {
        return match ($listing->status) {
            ListingStatus::UnderOffer => 'under_offer',
            ListingStatus::Sold => 'sold',
            ListingStatus::NotSold => 'not_sold',
            ListingStatus::NoBids => 'no_bids',
            ListingStatus::Closed => 'closed',
            default => null,
        };
    }

    /**
     * What everyone may see, also sent in the live feed event (B7).
     *
     * @return array<string, mixed>
     */
    public static function publicPart(Listing $listing, AuctionState $state): array
    {
        $tz = (string) config('platform.display_timezone');
        $recent = Bid::where('listing_id', $listing->id)->whereNull('voided_at')->orderByDesc('id')->limit(10)->get();

        return [
            'kind' => 'open',
            'outcome' => self::outcome($listing),
            'result' => SoldResult::for($listing), // price, date, bidder number; never a name
            'mining' => $listing->category->slug === 'mining', // S1: "Preferred bidder: subject to approvals"
            'current' => $state->current_amount,
            'leaderNumber' => $state->leader_approval_id ? BidderApproval::whereKey($state->leader_approval_id)->value('bidder_number') : null,
            'next' => Increments::next($state->current_amount, (int) $listing->minimum_price),
            'endsAt' => $state->ends_at->toIso8601String(),
            'endsAtText' => $state->ends_at->setTimezone($tz)->format('j F Y, H:i').' SAST',
            'extensions' => $state->extensions,
            'bidCount' => $state->bid_count,
            'paused' => $state->paused_at !== null,
            'closed' => $state->ends_at->isPast() || $listing->status !== ListingStatus::Live,
            'recent' => $recent->map(fn (Bid $b) => ['number' => $b->bidder_number, 'amount' => $b->amount, 'kind' => $b->kind, 'time' => $b->placed_at->setTimezone($tz)->format('H:i:s')])->values()->all(),
        ];
    }
}
