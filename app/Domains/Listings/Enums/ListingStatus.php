<?php

namespace App\Domains\Listings\Enums;

/**
 * Listing workflow (sprint 3 spec 5.5). Closed, processing, sold and
 * no-bids states arrive with the bidding and settlement sprints.
 */
enum ListingStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::ChangesRequested => 'Changes requested',
            self::Scheduled => 'Coming soon',
            self::Live => 'Live',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /** Visible on the public website. */
    public function isPublic(): bool
    {
        return in_array($this, [self::Scheduled, self::Live], true);
    }

    /** Listings managers can still edit content. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }
}
