<?php

namespace App\Domains\Platform\Support;

use App\Domains\Cms\Settings\HomeSettings;
use App\Domains\Cms\Support\BrandImages;
use App\Domains\Listings\Models\Listing;
use App\Domains\Listings\Public\PublicListing;

/** The sign-in photo: the uploaded one, else the featured or a live listing's photo, else the home banner photo. Never breaks a page. */
final class AuthCover
{
    public static function url(): ?string
    {
        $uploaded = BrandImages::url('auth');
        if ($uploaded !== null) {
            return $uploaded;
        }
        try {
            $featured = app(HomeSettings::class)->featured_listing;
            $query = Listing::public()->with(['category', 'city', 'media'])->whereHas('media');
            $listing = ($featured ? (clone $query)->where('ulid', $featured)->first() : null) ?? $query->latest('id')->first();

            return ($listing ? PublicListing::card($listing)['image'] : null) ?? BrandImages::url('hero');
        } catch (\Throwable) {
            return null;
        }
    }
}
