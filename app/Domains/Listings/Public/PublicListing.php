<?php

namespace App\Domains\Listings\Public;

use App\Domains\Catalogue\Enums\DocumentVisibility;
use App\Domains\Catalogue\Models\CategoryAttribute;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use App\Domains\Premium\Support\Badges;
use App\Domains\Settings\Settings\AuctionSettings;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The ONLY way listing data reaches public pages. Built from an explicit
 * allow-list: seller terms, the fee, the street address and review notes
 * are never included (Listing::INTERNAL). Map positions are rounded to
 * about 1 km so the exact site is not revealed.
 */
final class PublicListing
{
    public const VERIFICATION_NOTICE = 'This listing is subject to verification. A winning bid does not by itself transfer a mining right or guarantee regulatory approval.';

    /**
     * Compact form for cards and map pins.
     *
     * @return array<string, mixed>
     */
    public static function card(Listing $listing): array
    {
        return [
            'ulid' => $listing->ulid,
            'title' => $listing->title,
            'url' => self::url($listing),
            'category' => $listing->category->name,
            'categorySlug' => $listing->category->slug,
            'city' => $listing->city?->name,
            'province' => $listing->city?->province->name,
            'suburb' => $listing->suburb,
            'image' => self::image($listing->getFirstMedia('photos'), 'card'),
            'imageAlt' => (string) ($listing->getFirstMedia('photos')?->getCustomProperty('alt') ?? ''),
            'minimum' => $listing->minimum_price,
            'status' => self::status($listing),
            'startsAt' => $listing->starts_at?->toIso8601String(),
            'endsAt' => $listing->ends_at?->toIso8601String(),
            // Pre-formatted in SAST so server and browser render identical text.
            'opensLabel' => $listing->starts_at?->copy()->setTimezone((string) config('platform.display_timezone'))->format('j F Y, H:i'),
            'closesLabel' => $listing->ends_at?->copy()->setTimezone((string) config('platform.display_timezone'))->format('j F Y, H:i'),
            'renovated' => $listing->renovation_cost > 0,
            'featured' => $listing->is_featured,
            'badges' => Badges::for($listing),
            'auctionType' => $listing->auction_type->value,
            'facts' => self::facts($listing, cardOnly: true),
            'position' => self::position($listing),
        ];
    }

    /**
     * Full form for the listing page.
     *
     * @return array<string, mixed>
     */
    public static function detail(Listing $listing): array
    {
        $listing->loadMissing(['category.documentTypes', 'category.attributeDefinitions', 'attributeValues', 'city.province']);

        return self::card($listing) + [
            'summary' => $listing->summary,
            'description' => $listing->description,
            'videoEmbed' => self::videoEmbed($listing->video_url),
            'gallery' => $listing->getMedia('photos')->map(fn (Media $m): array => [
                'src' => self::image($m, 'large'),
                'thumb' => self::image($m, 'thumb'),
                'alt' => (string) $m->getCustomProperty('alt', ''),
            ])->values()->all(),
            'floorPlans' => $listing->getMedia('floor_plans')->map(fn (Media $m): array => [
                'src' => $m->mime_type === 'application/pdf' ? $m->getUrl() : self::image($m, 'large'),
                'pdf' => $m->mime_type === 'application/pdf',
                'name' => $m->name,
            ])->values()->all(),
            'keyFacts' => self::facts($listing, cardOnly: false),
            'deposit' => $listing->deposit_amount,
            'depositTerms' => $listing->deposit_terms->value,
            'biddingTerms' => $listing->bidding_terms,
            'notice' => $listing->category->requires_compliance_notice ? self::VERIFICATION_NOTICE : null,
            // Names only: files are never public; access arrives with buyer verification (Sprint 4).
            'documents' => $listing->category->documentTypes
                ->filter(fn ($t) => $t->visibility !== DocumentVisibility::Internal)
                ->map(fn ($t): array => ['name' => $t->name, 'access' => $t->visibility === DocumentVisibility::ApprovedNda ? 'Approved bidders after signing an NDA' : 'Verified bidders'])
                ->values()->all(),
        ];
    }

    public static function url(Listing $listing): string
    {
        return route('auctions.show', [
            'category' => $listing->category->slug,
            'city' => $listing->city->slug ?? 'south-africa',
            'listing' => $listing->slug,
        ]);
    }

    /**
     * @return array{key: string, label: string}
     */
    public static function status(Listing $listing): array
    {
        $finished = match ($listing->status) {
            ListingStatus::Closed => ['key' => 'closed', 'label' => 'Closed'],
            // S1: a winning bid does not by itself transfer a mining right.
            ListingStatus::Sold => ['key' => 'sold', 'label' => $listing->category->slug === 'mining' ? 'Preferred bidder: subject to approvals' : 'Sold'],
            ListingStatus::NoBids => ['key' => 'closed', 'label' => 'Closed, no bids'],
            default => null,
        };
        if ($finished !== null) {
            return $finished;
        }

        if ($listing->status === ListingStatus::Scheduled) {
            return ['key' => 'soon', 'label' => 'Opens '.($listing->starts_at?->setTimezone((string) config('platform.display_timezone'))->format('j M') ?? 'soon')];
        }

        $closingWindow = app(AuctionSettings::class)->closing_soon_hours;
        if ($listing->ends_at !== null && $listing->ends_at->isFuture() && $listing->ends_at->diffInHours(now(), true) <= $closingWindow) {
            return ['key' => 'closing', 'label' => 'Closing soon'];
        }

        return ['key' => 'live', 'label' => 'Live'];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private static function facts(Listing $listing, bool $cardOnly): array
    {
        $listing->loadMissing(['category.attributeDefinitions', 'attributeValues']);
        $values = $listing->attributeValues->pluck('value', 'category_attribute_id');

        return array_values($listing->category->attributeDefinitions
            ->filter(fn (CategoryAttribute $f): bool => ($cardOnly ? $f->show_on_card : $f->show_in_key_facts) && filled($values[$f->id] ?? null))
            ->map(function (CategoryAttribute $f) use ($values): array {
                $value = (string) $values[$f->id];
                if ($f->type->value === 'boolean') {
                    $value = $value === 'yes' ? 'Yes' : 'No';
                } elseif ($f->type->value === 'number' && is_numeric($value)) {
                    $value = rtrim(rtrim(number_format((float) $value, 2, '.', ' '), '0'), '.');
                }

                $display = match (true) {
                    $f->unit !== null => "{$value} {$f->unit}",
                    $f->type->value === 'number' => "{$value} ".mb_strtolower($f->label),
                    default => $value,
                };

                return ['label' => $f->label, 'value' => $display];
            })
            ->all());
    }

    /** @return array{lat: float, lng: float}|null */
    private static function position(Listing $listing): ?array
    {
        if ($listing->latitude === null || $listing->longitude === null) {
            return null;
        }

        return ['lat' => round((float) $listing->latitude, 2), 'lng' => round((float) $listing->longitude, 2)];
    }

    private static function image(?Media $media, string $conversion): ?string
    {
        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion($conversion) ? $media->getUrl($conversion) : $media->getUrl();
    }

    /** Privacy-enhanced embeds only (spec D3). */
    private static function videoEmbed(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return "https://www.youtube-nocookie.com/embed/{$m[1]}";
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return "https://player.vimeo.com/video/{$m[1]}?dnt=1";
        }

        return null;
    }
}
