<?php

namespace App\Domains\Bidding\Support;

use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Screening;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Who may bid now (B1), checked inside the bid lock on every bid:
 * a buyer approved for this listing, deposit received, identity and
 * screening still valid, listing live, auction open and not paused.
 * Staff can never bid.
 */
final class BidEligibility
{
    public const SCREENING_DAYS = 30;

    public function assert(Listing $listing, User $user, AuctionState $state, CarbonImmutable $now): BidderApproval
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['bid' => $m]);

        if (! $user->isBuyer()) {
            $fail(__('Staff accounts cannot bid.'));
        }
        if ($listing->auction_type->value !== 'open') {
            $fail(__('This listing is a sealed tender; submit an offer instead.'));
        }
        if (! $listing->status->isPublic() || $listing->status->value !== 'live') {
            $fail(__('Bidding is not open on this listing.'));
        }
        if ($state->paused_at !== null) {
            $fail(__('Bidding is paused by the auctioneer. Please wait.'));
        }
        if ($state->ends_at->lessThanOrEqualTo($now)) {
            $fail(__('This auction has closed.'));
        }

        return $this->bidder($listing, $user, $now);
    }

    /**
     * The bidder checks shared by open bidding and sealed tenders: approved
     * for this listing, deposit received, identity and screening valid.
     */
    public function bidder(Listing $listing, User $user, CarbonImmutable $now): BidderApproval
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['bid' => $m]);
        if (! $user->isBuyer()) {
            $fail(__('Staff accounts cannot bid.'));
        }

        $approval = BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->first();
        if ($approval === null || $approval->status !== 'approved' || $approval->bidder_number === null) {
            $fail(__('You’re not approved to bid on this listing yet.'));
        }
        /** @var BidderApproval $approval */
        if (! Deposit::where('bidder_approval_id', $approval->id)->whereIn('status', ['received'])->exists() && (int) $listing->deposit_amount > 0) {
            $fail(__('Your deposit hasn’t been received yet.'));
        }
        if (! Verification::where('user_id', $user->id)->where('status', 'verified')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))->exists()) {
            $fail(__('Your identity verification has expired. Please renew it.'));
        }
        $screen = Screening::where('user_id', $user->id)->latest('screened_at')->first();
        if ($screen === null || $screen->result !== 'clear' || $screen->screened_at->lt($now->subDays(self::SCREENING_DAYS))) {
            $fail(__('Your bidding approval needs a quick re-check by our team. We’ll be in touch.'));
        }

        return $approval;
    }
}
