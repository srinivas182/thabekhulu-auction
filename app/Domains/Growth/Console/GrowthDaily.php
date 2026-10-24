<?php

namespace App\Domains\Growth\Console;

use App\Domains\Bidding\Models\AuctionState;
use App\Domains\Growth\Notifications\GrowthMail;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\ListingSearch;
use App\Domains\Listings\Public\PublicListing;
use App\Domains\Payments\Models\Deposit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Saved-search digests (each listing once per search) and 24-hour watchlist reminders (once). */
class GrowthDaily extends Command
{
    protected $signature = 'growth:alerts';

    protected $description = 'Send saved-search digests and watchlist closing reminders';

    public function handle(): int
    {
        $digests = 0;
        foreach (DB::table('saved_searches')->get() as $search) {
            $new = [];
            foreach (ListingSearch::run((array) json_decode((string) $search->filters, true), 200)->items() as $l) {
                if (DB::table('saved_search_hits')->insertOrIgnore(['saved_search_id' => $search->id, 'listing_id' => $l->id, 'created_at' => now()]) === 1) {
                    $new[] = $l;
                }
            }
            $user = User::find((int) $search->user_id);
            if ($new === [] || $user === null) {
                continue;
            }
            $user->notify(new GrowthMail('saved_search', __('New auctions for “:name”', ['name' => $search->name]),
                array_map(fn (Listing $l) => $l->title.' · '.url(PublicListing::url($l)), $new),
                __('See all matches'), url('/auctions?'.http_build_query((array) json_decode((string) $search->filters, true)))));
            DB::table('saved_searches')->where('id', $search->id)->update(['last_alerted_at' => now()]);
            $digests++;
        }

        $reminders = 0;
        foreach (AuctionState::with('listing')->whereNull('paused_at')->where('ends_at', '>', now())->where('ends_at', '<=', now()->addDay())->get() as $state) {
            $bidders = Deposit::where('listing_id', $state->listing_id)->where('status', 'received')->pluck('user_id')->all(); // bidders get their own alerts
            foreach (DB::table('watchlist_items')->where('listing_id', $state->listing_id)->whereNotIn('user_id', $bidders)->pluck('user_id') as $userId) {
                try {
                    DB::table('auction_alerts')->insert(['listing_id' => $state->listing_id, 'user_id' => $userId, 'kind' => 'watch_24', 'created_at' => now()]);
                } catch (UniqueConstraintViolationException) {
                    continue;
                }
                User::find((int) $userId)?->notify(new GrowthMail('watch', __(':title closes within 24 hours', ['title' => $state->listing->title]), [
                    __('An auction on your watchlist closes within 24 hours.'),
                    __('To bid, you need to be approved for this auction with your deposit paid.'),
                ], __('View the auction'), url(PublicListing::url($state->listing))));
                $reminders++;
            }
        }
        $this->info("{$digests} saved-search digests, {$reminders} watchlist reminders.");

        return self::SUCCESS;
    }
}
