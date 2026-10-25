<?php

namespace App\Domains\Demo\Console;

use App\Domains\Audit\Support\Audit;
use App\Domains\Demo\Support\DemoContent;
use Illuminate\Console\Command;

/** Removes all sample content before launch. Samples with real activity (bids, deposits, tickets) are kept for a person to decide. */
class DemoRemove extends Command
{
    protected $signature = 'demo:remove {--force : Skip the confirmation}';

    protected $description = 'Remove the sample listings, event and articles';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Remove all sample listings, the sample event and sample articles?')) {
            return self::SUCCESS;
        }
        $done = DemoContent::remove();
        Audit::record('demo', 'removed', "Removed sample content: {$done['listings']} listings, {$done['events']} events, {$done['posts']} articles");
        $this->info("Removed {$done['listings']} listings, {$done['events']} events and {$done['posts']} articles.");
        foreach ($done['kept'] as $slug) {
            $this->warn("Kept {$slug}: it has bids, deposits or other activity. Remove it in the back office if it isn't needed.");
        }

        return self::SUCCESS;
    }
}
