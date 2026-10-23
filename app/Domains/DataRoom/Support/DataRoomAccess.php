<?php

namespace App\Domains\DataRoom\Support;

use App\Domains\Catalogue\Enums\DocumentVisibility;
use App\Domains\DataRoom\Settings\DataRoomSettings;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Models\ListingDocument;
use App\Domains\Verification\Models\BidderApproval;
use App\Domains\Verification\Models\Verification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who may open which listing document (sprint 4 spec 7):
 * - "verified bidders": any buyer with an in-date identity verification;
 * - "approved bidders after NDA": approved for this listing and has accepted the current NDA;
 * - internal: never.
 */
final class DataRoomAccess
{
    public function isVerified(User $user): bool
    {
        return Verification::where('user_id', $user->id)->where('status', 'verified')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }

    public function approval(User $user, Listing $listing): ?BidderApproval
    {
        return BidderApproval::where('listing_id', $listing->id)->where('user_id', $user->id)->first();
    }

    public function ndaAccepted(User $user, Listing $listing): bool
    {
        return DB::table('nda_acceptances')->where('listing_id', $listing->id)->where('user_id', $user->id)
            ->where('nda_version', app(DataRoomSettings::class)->nda_version)->exists();
    }

    /** A buyer may sign the NDA once they have applied (pending or approved) and are verified. */
    public function canSignNda(User $user, Listing $listing): bool
    {
        return $this->isVerified($user) && in_array($this->approval($user, $listing)?->status, ['pending', 'approved'], true);
    }

    public function canOpen(User $user, ListingDocument $document): bool
    {
        $document->loadMissing(['documentType', 'listing']);
        $listing = $document->listing()->first();
        if ($listing === null || ! $user->isBuyer() || ! $listing->status->isPublic()) {
            return false;
        }

        return match ($document->documentType->visibility) {
            DocumentVisibility::Internal => false,
            DocumentVisibility::Verified => $this->isVerified($user),
            DocumentVisibility::ApprovedNda => $this->isVerified($user)
                && $this->approval($user, $listing)?->status === 'approved'
                && $this->ndaAccepted($user, $listing),
        };
    }
}
