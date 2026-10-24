<?php

namespace App\Domains\Listings\Enums;

/**
 * Listing workflow (sprint 3 spec 5.5). Closed, sold and no-bids arrive
 * with the auction close (sprint 6c); settlement states with sprint 7.
 */
enum ListingStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Withdrawn = 'withdrawn';
    case Closed = 'closed';      // bidding ended; results being determined (tenders await opening)
    case Sold = 'sold';          // winner recorded; pending settlement
    case NoBids = 'no_bids';     // closed without a valid bid

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::ChangesRequested => 'Changes requested',
            self::Scheduled => 'Coming soon',
            self::Live => 'Live',
            self::Withdrawn => 'Withdrawn',
            self::Closed => 'Closed',
            self::Sold => 'Sold, pending settlement',
            self::NoBids => 'Closed, no bids',
        };
    }

    /** Visible on the public website. */
    public function isPublic(): bool
    {
        return in_array($this, [self::Scheduled, self::Live], true);
    }

    /** Shown on the website, including finished auctions with their result. Bidding, applying and data rooms use isPublic(). */
    public function isShown(): bool
    {
        return $this->isPublic() || in_array($this, [self::Closed, self::Sold, self::NoBids], true);
    }

    /** Listings managers can still edit content. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }
}
