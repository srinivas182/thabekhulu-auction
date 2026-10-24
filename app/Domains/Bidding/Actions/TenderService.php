<?php

namespace App\Domains\Bidding\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Support\BidEligibility;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sealed tenders (B8). Offers are encrypted and only ever compared with the
 * same bidder's previous offer (raise-only) until a two-person opening after
 * the deadline. Highest offer wins; ties go to the earlier offer.
 */
class TenderService
{
    public function __construct(private readonly BidEligibility $eligibility, private readonly AuctionCloser $closer) {}

    public function submit(Listing $listing, User $user, int $amount): void
    {
        $now = CarbonImmutable::now();
        if ($listing->auction_type->value !== 'sealed' || $listing->status !== ListingStatus::Live) {
            throw ValidationException::withMessages(['bid' => __('This listing is not accepting sealed offers.')]);
        }
        if ($listing->ends_at === null || $listing->ends_at->lessThanOrEqualTo($now)) {
            throw ValidationException::withMessages(['bid' => __('The tender has closed.')]);
        }
        $approval = $this->eligibility->bidder($listing, $user, $now);
        if ($amount < (int) $listing->minimum_price) {
            throw ValidationException::withMessages(['amount' => __('Offers start at R :min.', ['min' => number_format((int) $listing->minimum_price, 0, '.', ' ')])]);
        }

        DB::transaction(function () use ($listing, $approval, $amount, $now, $user) {
            $existing = DB::table('tender_offers')->where('listing_id', $listing->id)->where('bidder_approval_id', $approval->id)->lockForUpdate()->first();
            if ($existing !== null && $amount <= (int) Crypt::decryptString((string) $existing->amount)) {
                throw ValidationException::withMessages(['amount' => __('A new offer must be higher than your previous offer.')]);
            }
            DB::table('tender_offers')->updateOrInsert(['listing_id' => $listing->id, 'bidder_approval_id' => $approval->id], [
                'amount' => Crypt::encryptString((string) $amount), 'submitted_at' => $now, 'revisions' => $existing ? $existing->revisions + 1 : 0,
            ]);
            Audit::record('bidding', 'tender_offer', "Bidder #{$approval->bidder_number} ".($existing ? 'raised' : 'submitted').' a sealed offer', $listing, $user); // never the amount
        });
    }

    /**
     * The bidder may see their own offer.
     *
     * @return array{amount: int, at: string}|null
     */
    public function ownOffer(Listing $listing, int $approvalId): ?array
    {
        $row = DB::table('tender_offers')->where('listing_id', $listing->id)->where('bidder_approval_id', $approvalId)->first();

        return $row ? ['amount' => (int) Crypt::decryptString((string) $row->amount), 'at' => (string) $row->submitted_at] : null;
    }

    public function requestOpening(Listing $listing, User $staff): void
    {
        if ($listing->status !== ListingStatus::Closed || $listing->auction_type->value !== 'sealed') {
            throw ValidationException::withMessages(['tender' => __('The tender can be opened only after its deadline.')]);
        }
        DB::table('tender_openings')->insertOrIgnore(['listing_id' => $listing->id, 'requested_by' => $staff->id, 'requested_at' => now()]);
        Audit::record('bidding', 'tender_opening_requested', "Requested opening of the tender for {$listing->title}", $listing, $staff);
    }

    /** The second person opens the offers, records the result and hands deposits on. */
    public function confirmOpening(Listing $listing, User $staff): void
    {
        $result = DB::transaction(function () use ($listing, $staff) {
            $opening = DB::table('tender_openings')->where('listing_id', $listing->id)->lockForUpdate()->first();
            if ($opening === null || $opening->opened_at !== null) {
                throw ValidationException::withMessages(['tender' => __('Request the opening first.')]);
            }
            if ((int) $opening->requested_by === $staff->id) {
                throw ValidationException::withMessages(['tender' => __('A different person must confirm the opening.')]);
            }

            $offers = DB::table('tender_offers')->where('listing_id', $listing->id)->get()
                ->map(fn ($o) => ['approval' => (int) $o->bidder_approval_id, 'amount' => (int) Crypt::decryptString((string) $o->amount), 'at' => (string) $o->submitted_at])
                ->sort(fn ($a, $b) => [$b['amount'], $a['at']] <=> [$a['amount'], $b['at']])->values();

            $winner = $offers[0] ?? null;
            $runnerUp = $offers[1] ?? null;
            $this->closer->record($listing, 'sealed', $winner ? 'sold' : 'no_bids', $winner['approval'] ?? null, $winner['amount'] ?? null, $runnerUp['approval'] ?? null, $runnerUp['amount'] ?? null, $offers->count());
            DB::table('tender_openings')->where('listing_id', $listing->id)->update(['opened_by' => $staff->id, 'opened_at' => now()]);
            Audit::record('bidding', 'tender_opened', "Opened the tender for {$listing->title} ({$offers->count()} offers)", $listing, $staff);

            return [$winner['approval'] ?? null, $winner['amount'] ?? null, $runnerUp['approval'] ?? null];
        });

        $this->closer->handOff($listing->fresh() ?? $listing, $result[0], $result[1], $result[2]);
    }
}
