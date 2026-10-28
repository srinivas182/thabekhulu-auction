<?php

namespace App\Domains\Platform\Support;

use App\Domains\Cms\Settings\HomeSettings;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;

/** A real property photo for the sign-in pages: the featured auction's, else any live listing's. Never breaks a page. */
final class AuthCover
{
    public static function url(): ?string
    {
        try {
            $featured = app(HomeSettings::class)->featured_listing;
            $query = Listing::public()->with(['category', 'city', 'media'])->whereHas('media');
            $listing = ($featured ? (clone $query)->where('ulid', $featured)->first() : null) ?? $query->latest('id')->first();

            return $listing ? PublicListing::card($listing)['image'] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
