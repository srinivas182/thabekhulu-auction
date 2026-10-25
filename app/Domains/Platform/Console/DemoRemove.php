<?php

namespace App\Domains\Platform\Console;

use App\Domains\Cms\Models\Post;
use App\Domains\Events\Models\Event;
use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Removes everything DemoSeeder created (slugs starting "sample-"), photos included. Run before launch. */
class DemoRemove extends Command
{
    protected $signature = 'demo:remove {--force : Skip the confirmation}';

    protected $description = 'Remove the sample listings, event and articles';

    public function handle(): int
    {
        $listings = Listing::where('slug', 'like', 'sample-%')->get();
        if (! $this->option('force') && ! $this->confirm("Remove {$listings->count()} sample listings, the sample event and articles?")) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($listings) {
            foreach ($listings as $l) {
                DB::table('auction_results')->where('listing_id', $l->id)->delete();
                DB::table('bids')->where('listing_id', $l->id)->delete(); // bids are append-only in the app; demo rows are the exception
                DB::table('auction_states')->where('listing_id', $l->id)->delete();
                DB::table('bidder_approvals')->where('listing_id', $l->id)->delete();
                $l->clearMediaCollection('photos');
                $l->delete();
            }
            Event::where('slug', 'like', 'sample-%')->delete();
            foreach (Post::where('slug', 'like', 'sample-%')->get() as $p) {
                $p->clearMediaCollection('cover');
                $p->delete();
            }
            User::where('email', 'sample.bidder@thabekhulu.invalid')->delete();
        });
        $this->info('Sample content removed.');

        return self::SUCCESS;
    }
}
