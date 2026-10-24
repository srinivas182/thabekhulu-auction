<?php

namespace App\Domains\Premium\Models;

use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $listing_id
 * @property int $category_id
 * @property int|null $city_id
 * @property int $position
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property-read Listing $listing
 * @property-read Category $category
 * @property-read City|null $city
 */
class PremiumSlot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    /**
     * Slots active today (SAST).
     *
     * @param  Builder<PremiumSlot>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $today = CarbonImmutable::today((string) config('platform.display_timezone'))->toDateString();
        $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
