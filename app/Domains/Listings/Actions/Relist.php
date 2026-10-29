<?php

namespace App\Domains\Listings\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Models\Bid;
use App\Domains\Bidding\Models\MaxBid;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Relists a listing that closed without a valid bid (PRD: hidden, relistable).
 * Old bids are voided (the record stays), the previous auction state, result
 * and tender data are cleared, earlier applicants re-apply, and the listing
 * returns to Draft for new dates and review.
 */
class Relist
{
    public function handle(Listing $listing, User $by): void
    {
        if (! in_array($listing->status, [ListingStatus::NoBids, ListingStatus::NotSold], true)) {
            throw ValidationException::withMessages(['status' => __('Only listings that closed without bids can be relisted.')]);
        }

        DB::transaction(function () use ($listing, $by) {
            Bid::where('listing_id', $listing->id)->whereNull('voided_at')->get()
                ->each(fn (Bid $b) => $b->forceFill(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => 'Relisted'])->save());
            MaxBid::where('listing_id', $listing->id)->whereNull('voided_at')->update(['voided_at' => now()]);
            DB::table('auction_states')->where('listing_id', $listing->id)->delete();
            DB::table('auction_results')->where('listing_id', $listing->id)->delete();
            DB::table('tender_offers')->where('listing_id', $listing->id)->delete();
            DB::table('tender_openings')->where('listing_id', $listing->id)->delete();
            BidderApproval::where('listing_id', $listing->id)->whereIn('status', ['pending', 'approved'])
                ->update(['status' => 'revoked', 'reason' => 'The listing was relisted. Please apply again.']);
            $listing->forceFill(['status' => ListingStatus::Draft])->save();
        });

        Audit::record('listings', 'relisted', "Relisted {$listing->title} after it closed without bids", $listing, $by);
    }
}
