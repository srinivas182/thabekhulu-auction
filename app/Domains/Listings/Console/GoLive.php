<?php

namespace App\Domains\Listings\Console;

use App\Domains\Listings\Actions\ListingWorkflow;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use Illuminate\Console\Command;

class GoLive extends Command
{
    /** @var string */
    protected $signature = 'listings:go-live';

    /** @var string */
    protected $description = 'Make scheduled listings live when their auction start time arrives';

    public function handle(ListingWorkflow $workflow): int
    {
        $count = 0;

        Listing::where('status', ListingStatus::Scheduled)
            ->where('starts_at', '<=', now())
            ->each(function (Listing $listing) use ($workflow, &$count): void {
                $workflow->goLive($listing);
                $count++;
            });

        $this->info("{$count} listing(s) went live.");

        return self::SUCCESS;
    }
}
