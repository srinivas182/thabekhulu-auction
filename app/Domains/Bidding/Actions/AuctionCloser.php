<?php

namespace App\Domains\Bidding\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Bidding\Notifications\AuctionResult;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Payments\Actions\RefundService;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Payments\Settings\PaymentSettings;
use App\Domains\Verification\Models\BidderApproval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closes auctions (sprint 6 spec 7). Open auctions: once the possibly
 * extended end has passed, under the bid lock, record winner and runner-up
 * and mark the listing Sold or No bids. Sealed tenders: mark Closed and wait
 * for the two-person opening. Then every deposit is handed on: unsuccessful
 * bidders scheduled for refund, the runner-up's held, the winner's kept.
 */
class AuctionCloser
{
    public function __construct(private readonly RefundService $refunds) {}

    /** @return 'closed'|'awaiting_opening'|'not_yet'|'skipped' */
    public function close(Listing $listing): string
    {
        if ($listing->status !== ListingStatus::Live) {
            return 'skipped';
        }

        if ($listing->auction_type->value === 'sealed') {
            if ($listing->ends_at === null || $listing->ends_at->isFuture()) {
                return 'not_yet';
            }
            $listing->forceFill(['status' => ListingStatus::Closed])->save();
            Audit::record('bidding', 'tender_closed', "Tender closed for {$listing->title}; awaiting two-person opening", $listing);

            return 'awaiting_opening';
        }

        BidEngine::state($listing);
        $result = DB::transaction(function () use ($listing) {
            /** @var AuctionState $state */
            $state = AuctionState::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            if ($state->paused_at !== null || $state->ends_at->isFuture()) {
                return null; // soft close moved the end, or the auctioneer paused it
            }

            $runnerUp = $state->leader_approval_id === null ? null : Bid::where('listing_id', $listing->id)->whereNull('voided_at')
                ->where('bidder_approval_id', '!=', $state->leader_approval_id)->orderByDesc('amount')->orderBy('id')->first();

            $outcome = $state->leader_approval_id ? 'sold' : 'no_bids';
            $this->record($listing, 'open', $outcome, $state->leader_approval_id, $state->current_amount, $runnerUp?->bidder_approval_id, $runnerUp?->amount,
                Bid::where('listing_id', $listing->id)->whereNull('voided_at')->distinct()->count('bidder_approval_id'));

            BidEngine::announce($listing, $state);

            return [$state->leader_approval_id, $state->current_amount, $runnerUp?->bidder_approval_id];
        });

        if ($result === null) {
            return 'not_yet';
        }
        $this->handOff($listing->fresh() ?? $listing, $result[0], $result[1], $result[2]);

        return 'closed';
    }

    /** Writes the result and the final listing status (inside the caller's transaction). */
    public function record(Listing $listing, string $kind, string $outcome, ?int $winner, ?int $amount, ?int $runnerUp, ?int $runnerUpAmount, int $bidders): void
    {
        DB::table('auction_results')->insert([
            'listing_id' => $listing->id, 'kind' => $kind, 'outcome' => $outcome, 'winner_approval_id' => $winner, 'winning_amount' => $amount,
            'runner_up_approval_id' => $runnerUp, 'runner_up_amount' => $runnerUpAmount, 'bidders' => $bidders, 'closed_at' => now(),
        ]);
        $listing->forceFill(['status' => $outcome === 'sold' ? ListingStatus::Sold : ListingStatus::NoBids])->save();

        Audit::record('bidding', 'auction_closed', "{$listing->title} closed: ".($outcome === 'sold' ? 'winning bid R '.number_format((int) $amount, 0, '.', ' ') : 'no bids'), $listing, null, [
            'winner_approval' => $winner, 'runner_up_approval' => $runnerUp, 'bidders' => $bidders,
        ]);
    }

    /** Deposits after the close: winner kept (settlement), runner-up held, everyone else refunded. */
    public function handOff(Listing $listing, ?int $winner, ?int $amount, ?int $runnerUp): void
    {
        $holdDays = app(PaymentSettings::class)->runner_up_hold_days;

        foreach (Deposit::with('user')->where('listing_id', $listing->id)->where('status', 'received')->get() as $deposit) {
            if ($deposit->bidder_approval_id === $winner) {
                $deposit->user->notify(new AuctionResult($listing, 'won', $amount, null));

                continue;
            }

            $isRunnerUp = $deposit->bidder_approval_id === $runnerUp;
            $due = $isRunnerUp ? CarbonImmutable::today((string) config('platform.display_timezone'))->addDays($holdDays) : null;
            try {
                $refund = $this->refunds->schedule($deposit, $isRunnerUp ? 'runner_up_released' : 'unsuccessful', null, $due);
            } catch (\Throwable $e) {
                // One problem deposit must not stop the close for everyone else; finance still sees it under "Deposits held".
                Log::error('Could not schedule a refund at auction close', ['deposit' => $deposit->reference, 'error' => $e->getMessage()]);
                Audit::record('payments', 'refund_not_scheduled', "Refund of {$deposit->reference} could not be scheduled at close: {$e->getMessage()}", $deposit);

                continue;
            }

            $deposit->user->notify(new AuctionResult($listing, $isRunnerUp ? 'runner_up' : 'lost', null, $refund->due_on->format('j F Y')));
        }

        // Applications that never paid a deposit simply end.
        BidderApproval::where('listing_id', $listing->id)->where('status', 'pending')->update(['status' => 'rejected', 'reason' => 'The auction closed.']);
    }
}
