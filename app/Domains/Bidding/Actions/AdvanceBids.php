<?php

namespace App\Domains\Bidding\Actions;

use App\Domains\Bidding\Models\AdvanceBid;
use App\Domains\Bidding\Support\BidEligibility;
use App\Domains\Bidding\Support\Increments;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Advance maximums: set before bidding opens, placed through the normal bid
 * engine when the listing goes live, in the order they were set. Proxy
 * bidding, increments and "earlier maximum wins ties" all apply as usual.
 */
final class AdvanceBids
{
    public function __construct(private readonly BidEngine $engine, private readonly BidEligibility $eligibility) {}

    /** The buyer sets or changes their advance maximum (only before bidding opens). */
    public function set(Listing $listing, User $user, int $amount): AdvanceBid
    {
        $this->assertOpenForAdvance($listing);
        $approval = $this->eligibility->bidder($listing, $user, CarbonImmutable::now()); // approved, deposit received, identity and screening valid
        if ($amount < (int) $listing->minimum_price) {
            throw ValidationException::withMessages(['maximum' => __('Your maximum must be at least the minimum bid of R :min.', ['min' => number_format((int) $listing->minimum_price, 0, '.', ' ')])]);
        }

        $bid = AdvanceBid::firstOrNew(['listing_id' => $listing->id, 'bidder_approval_id' => $approval->id]);
        // Changing the amount keeps your place in the queue only if you raise it; lowering it moves you behind earlier maximums.
        $lowered = $bid->exists && $amount < (int) $bid->amount;
        $bid->forceFill(['amount' => $amount, 'set_at' => $bid->exists && ! $lowered ? $bid->set_at : now()])->save();

        return $bid;
    }

    public function remove(Listing $listing, User $user): void
    {
        $this->assertOpenForAdvance($listing);
        $approval = $this->eligibility->bidder($listing, $user, CarbonImmutable::now());
        AdvanceBid::where('listing_id', $listing->id)->where('bidder_approval_id', $approval->id)->whereNull('applied_at')->delete();
    }

    /**
     * Called when the listing goes live.
     *
     * @return array{placed: int, outbid: int}
     */
    public function apply(Listing $listing): array
    {
        $done = ['placed' => 0, 'outbid' => 0];
        $pending = AdvanceBid::with('approval.user')->where('listing_id', $listing->id)->whereNull('applied_at')->orderBy('set_at')->orderBy('id')->get();

        foreach ($pending as $advance) {
            $state = BidEngine::state($listing->fresh() ?? $listing);
            $next = Increments::next($state->current_amount, (int) $listing->minimum_price);
            $max = (int) $advance->amount;
            $result = 'outbid';
            if ($max >= $next) {
                try {
                    $this->engine->place($listing->fresh() ?? $listing, $advance->approval->user, $next, $max);
                    $result = 'placed';
                } catch (ValidationException) {
                    $result = 'outbid'; // e.g. the buyer is no longer eligible; recorded, not retried
                }
            }
            $advance->forceFill(['applied_at' => now(), 'result' => $result])->save();
            $done[$result]++;
        }

        return $done;
    }

    private function assertOpenForAdvance(Listing $listing): void
    {
        if ($listing->auction_type->value !== 'open') {
            throw ValidationException::withMessages(['maximum' => __('This is a sealed tender: submit an offer instead.')]);
        }
        if ($listing->status !== ListingStatus::Scheduled) {
            throw ValidationException::withMessages(['maximum' => __('Advance maximums can only be set before bidding opens.')]);
        }
    }
}
