<?php

namespace App\Domains\Listings\Public;

use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Listings\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public auction search. Database queries on indexed columns; one class so
 * it can move to Meilisearch later without touching pages (ADR 0007).
 */
final class ListingSearch
{
    public const SORTS = ['closing' => 'Closing soonest', 'newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];

    /**
     * @param  array{category?: string|null, province?: string|null, city?: string|null, min?: int|null, max?: int|null, within?: string|null, status?: string|null, renovated?: bool, sort?: string|null}  $f
     * @return LengthAwarePaginator<int, Listing>
     */
    public static function run(array $f, int $perPage = 12): LengthAwarePaginator
    {
        $query = Listing::query()
            ->public()
            ->with(['category.attributeDefinitions', 'attributeValues', 'city.province', 'media'])
            ->whereHas('category', fn (Builder $c) => $c->where('is_visible', true))
            ->when($f['category'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('category', fn (Builder $c) => $c->where('slug', $slug)))
            ->when($f['province'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('city.province', fn (Builder $p) => $p->where('slug', $slug)))
            ->when($f['city'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('city', fn (Builder $c) => $c->where('slug', $slug)))
            ->when($f['min'] ?? null, fn (Builder $q, int $min) => $q->where('minimum_price', '>=', $min))
            ->when($f['max'] ?? null, fn (Builder $q, int $max) => $q->where('minimum_price', '<=', $max))
            ->when(($f['status'] ?? null) === 'live', fn (Builder $q) => $q->where('status', ListingStatus::Live))
            ->when(($f['status'] ?? null) === 'soon', fn (Builder $q) => $q->where('status', ListingStatus::Scheduled))
            ->when($f['renovated'] ?? false, fn (Builder $q) => $q->where('renovation_cost', '>', 0))
            ->when($f['within'] ?? null, function (Builder $q, string $within) {
                $hours = ['24h' => 24, '7d' => 168, '30d' => 720][$within] ?? null;
                if ($hours !== null) {
                    $q->where('status', ListingStatus::Live)->where('ends_at', '<=', now()->addHours($hours));
                }
            });

        // Premium slots (S10): when browsing a category, active slots come first by position.
        if (($f['category'] ?? null) !== null) {
            $cityId = ($f['city'] ?? null) !== null ? City::where('slug', $f['city'])->value('id') : null;
            $today = now((string) config('platform.display_timezone'))->toDateString();
            $query->orderByRaw(
                'coalesce((select min(ps.position) from premium_slots ps where ps.listing_id = listings.id and ps.category_id = listings.category_id'
                .' and (ps.city_id is null or ps.city_id = ?) and date(ps.starts_on) <= ? and date(ps.ends_on) >= ?), 99)', // DATE(): portable across MySQL and SQLite
                [$cityId ?? 0, $today, $today],
            );
        }

        match ($f['sort'] ?? 'closing') {
            'newest' => $query->latest('approved_at'),
            'price_asc' => $query->orderBy('minimum_price'),
            'price_desc' => $query->orderByDesc('minimum_price'),
            default => $query->orderByRaw('case when status = ? then 0 else 1 end', [ListingStatus::Live->value])->orderBy('ends_at'),
        };

        return $query->paginate($perPage)->withQueryString();
    }
}
