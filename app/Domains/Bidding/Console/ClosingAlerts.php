<?php

namespace App\Domains\Bidding\Console;

use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Bidding\Notifications\ClosingSoon;
use App\Domains\Payments\Models\Deposit;
use App\Domains\Verification\Models\BidderApproval;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Every minute: closing-soon alerts at 60 and 10 minutes, each sent once per bidder (soft close may move the end). */
class ClosingAlerts extends Command
{
    protected $signature = 'auctions:closing-alerts';

    protected $description = 'Send closing-soon alerts to bidders';

    public function handle(): int
    {
        $sent = 0;
        foreach ([60, 10] as $minutes) {
            $states = AuctionState::with('listing')->whereNull('paused_at')->where('ends_at', '>', now())->where('ends_at', '<=', now()->addMinutes($minutes))->get();

            foreach ($states as $state) {
                $approvals = BidderApproval::with('user')->where('listing_id', $state->listing_id)->where('status', 'approved')
                    ->whereIn('id', Deposit::where('listing_id', $state->listing_id)->where('status', 'received')->select('bidder_approval_id'))->get();

                foreach ($approvals as $approval) {
                    try {
                        DB::table('auction_alerts')->insert(['listing_id' => $state->listing_id, 'user_id' => $approval->user_id, 'kind' => "closing_{$minutes}", 'created_at' => now()]);
                    } catch (UniqueConstraintViolationException) {
                        continue; // already told
                    }
                    $approval->user->notify(new ClosingSoon($state->listing, $minutes, $state->leader_approval_id === $approval->id));
                    $sent++;
                }
            }
        }
        $this->info("{$sent} closing-soon alerts sent.");

        return self::SUCCESS;
    }
}
