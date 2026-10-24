<?php

namespace App\Domains\Bidding\Console;

use App\Domains\Bidding\Actions\AuctionCloser;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use Illuminate\Console\Command;

/** Every minute: close live auctions whose (possibly extended) end has passed. Safe to run repeatedly. */
class CloseAuctions extends Command
{
    protected $signature = 'auctions:close';

    protected $description = 'Close auctions that have ended and hand deposits to refunds';

    public function handle(AuctionCloser $closer): int
    {
        $done = 0;
        // Extensions only move the end later, so the original end having passed is the right filter;
        // the closer checks the live (possibly extended) end under the bid lock.
        foreach (Listing::where('status', ListingStatus::Live)->where('ends_at', '<=', now())->get() as $listing) {
            if (in_array($closer->close($listing), ['closed', 'awaiting_opening'], true)) {
                $done++;
            }
        }
        $resumed = $closer->resumeHandOffs();
        $this->info("{$done} auctions closed, {$resumed} interrupted hand-offs completed.");

        return self::SUCCESS;
    }
}
