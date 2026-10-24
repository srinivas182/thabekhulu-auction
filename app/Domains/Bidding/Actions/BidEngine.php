<?php

namespace App\Domains\Bidding\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Bidding\Models\MaxBid;
use App\Domains\Bidding\Support\BidEligibility;
use App\Domains\Bidding\Support\Increments;
use App\Domains\Listings\Models\Listing;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place a bid is accepted (sprint 6 spec 5).
 *
 * Every bid for a listing runs inside one transaction holding a row lock
 * on that listing's auction state (B4), so bids are processed one at a
 * time. Proxy maximums (B3): the leader keeps the lead while their maximum
 * covers the challenger, bidding only what is needed; on equal maximums the
 * earlier maximum wins. Soft close (B5) moves the end when a bid lands in
 * the closing window.
 */
class BidEngine
{
    public function __construct(private readonly BidEligibility $eligibility) {}

    /** The auction state for a listing, created from the listing's end time the first time. */
    public static function state(Listing $listing): AuctionState
    {
        return AuctionState::firstOrCreate(['listing_id' => $listing->id], ['ends_at' => $listing->ends_at ?? now()]);
    }

    /**
     * @return array{state: AuctionState, leading: bool, bids: list<Bid>}
     */
    public function place(Listing $listing, User $user, int $amount, ?int $maximum = null, ?string $ip = null): array
    {
        self::state($listing);

        return DB::transaction(function () use ($listing, $user, $amount, $maximum, $ip) {
            /** @var AuctionState $state */
            $state = AuctionState::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now();

            $approval = $this->eligibility->assert($listing, $user, $state, $now);
            $minimum = (int) $listing->minimum_price;
            $next = Increments::next($state->current_amount, $minimum);

            if ($maximum !== null && $maximum < $amount) {
                throw ValidationException::withMessages(['maximum' => __('Your maximum can’t be lower than your bid.')]);
            }

            $isLeader = $state->leader_approval_id === $approval->id;
            if ($isLeader) {
                return $this->raiseOwnMaximum($state, $approval, $maximum ?? $amount, $now);
            }
            if ($amount < $next) {
                throw ValidationException::withMessages(['amount' => __('The lowest bid you can place now is R :next.', ['next' => number_format($next, 0, '.', ' ')])]);
            }

            $mine = max($amount, $maximum ?? 0);
            $this->saveMaximum($listing, $approval, $mine, $now);

            $leaderMax = $state->leader_approval_id ? $this->maximumOf($listing->id, (int) $state->leader_approval_id) : null;
            $recorded = [];

            if ($state->leader_approval_id === null || $leaderMax === null || $mine > $leaderMax) {
                // Challenger takes the lead: the leader's proxy first rises to its maximum, if it had headroom.
                if ($leaderMax !== null && $leaderMax > (int) $state->current_amount) {
                    $recorded[] = $this->record($listing, (int) $state->leader_approval_id, $leaderMax, 'proxy', $now, null);
                }
                $price = $leaderMax === null ? $amount : max($amount, min($mine, $leaderMax + Increments::at($leaderMax)));
                $recorded[] = $this->record($listing, $approval->id, $price, $price === $amount ? 'manual' : 'proxy', $now, $ip);
                $state->leader_approval_id = $approval->id;
                $state->current_amount = $price;
            } else {
                // The leader's maximum covers the challenger: record the challenger's bid, then the leader's answer.
                $recorded[] = $this->record($listing, $approval->id, $mine, $mine === $amount ? 'manual' : 'proxy', $now, $ip);
                $answer = $mine === $leaderMax ? $leaderMax : min($leaderMax, $mine + Increments::at($mine));
                $recorded[] = $this->record($listing, (int) $state->leader_approval_id, $answer, 'proxy', $now, null);
                $state->current_amount = $answer;
            }

            $state->bid_count += count($recorded);
            $this->softClose($state, $now);
            $state->save();

            Audit::record('bidding', 'bid', "Bidder #{$approval->bidder_number} bid R ".number_format($amount, 0, '.', ' ')." on {$listing->title}", $listing, $user, [
                'amount' => $amount, 'maximum_set' => $maximum !== null, 'leader' => $state->leader_approval_id === $approval->id,
            ]);

            return ['state' => $state, 'leading' => $state->leader_approval_id === $approval->id, 'bids' => $recorded];
        });
    }

    /** @return array{state: AuctionState, leading: bool, bids: list<Bid>} */
    private function raiseOwnMaximum(AuctionState $state, BidderApproval $approval, int $maximum, CarbonImmutable $now): array
    {
        $current = $this->maximumOf($state->listing_id, $approval->id) ?? (int) $state->current_amount;
        if ($maximum <= $current) {
            throw ValidationException::withMessages(['maximum' => __('You’re already the highest bidder. To bid more, raise your maximum above R :max.', ['max' => number_format($current, 0, '.', ' ')])]);
        }
        $this->saveMaximum($state->listing, $approval, $maximum, $now, keepTime: true);

        return ['state' => $state, 'leading' => true, 'bids' => []];
    }

    private function saveMaximum(Listing $listing, BidderApproval $approval, int $amount, CarbonImmutable $now, bool $keepTime = false): void
    {
        $existing = MaxBid::where('listing_id', $listing->id)->where('bidder_approval_id', $approval->id)->whereNull('voided_at')->first();
        if ($existing !== null && $existing->value() >= $amount) {
            return;
        }
        // Raising a maximum keeps its original time only when the bidder already leads (B3 ties go to the earlier maximum).
        MaxBid::updateOrCreate(['listing_id' => $listing->id, 'bidder_approval_id' => $approval->id], [
            'amount' => (string) $amount, 'set_at' => $keepTime && $existing ? $existing->set_at : $now, 'voided_at' => null,
        ]);
    }

    private function maximumOf(int $listingId, int $approvalId): ?int
    {
        $max = MaxBid::where('listing_id', $listingId)->where('bidder_approval_id', $approvalId)->whereNull('voided_at')->first();

        return $max?->value();
    }

    private function record(Listing $listing, int $approvalId, int $amount, string $kind, CarbonImmutable $now, ?string $ip): Bid
    {
        $number = (string) BidderApproval::whereKey($approvalId)->value('bidder_number');

        return Bid::create([
            'listing_id' => $listing->id, 'bidder_approval_id' => $approvalId, 'bidder_number' => $number,
            'amount' => $amount, 'kind' => $kind, 'placed_at' => $now, 'ip_address' => $ip,
        ]);
    }

    private function softClose(AuctionState $state, CarbonImmutable $now): void
    {
        $s = app(AuctionSettings::class);
        if (! $s->soft_close_enabled) {
            return;
        }
        $window = $s->soft_close_window_minutes;
        if ($state->ends_at->subMinutes($window)->lessThanOrEqualTo($now)) {
            $state->ends_at = $now->addMinutes($s->soft_close_extension_minutes);
            $state->extensions++;
        }
    }
}
