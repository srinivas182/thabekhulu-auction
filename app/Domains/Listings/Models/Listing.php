<?php

namespace App\Domains\Listings\Models;

use App\Domains\Catalogue\Enums\DepositTerms;
use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\City;
use App\Domains\Listings\Enums\AuctionType;
use App\Domains\Listings\Enums\DealType;
use App\Domains\Listings\Enums\ListingStatus;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $ulid
 * @property int $category_id
 * @property int|null $city_id
 * @property string|null $suburb
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $video_url
 * @property DealType $deal_type
 * @property int|null $seller_net_price
 * @property int|null $seller_price
 * @property int $renovation_cost
 * @property int $fee_bps
 * @property int|null $minimum_price
 * @property int $deposit_amount
 * @property DepositTerms $deposit_terms
 * @property AuctionType $auction_type
 * @property int|null $seller_user_id
 * @property string|null $bidding_terms
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property ListingStatus $status
 * @property string|null $review_note
 * @property bool $is_featured
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 * @property-read City|null $city
 * @property-read User|null $updater
 */
class Listing extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** Seller terms: never serialised for public pages. */
    public const INTERNAL = ['seller_net_price', 'seller_price', 'renovation_cost', 'fee_bps', 'address', 'review_note'];

    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(function (Listing $listing): void {
            $listing->ulid ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'deal_type' => DealType::class,
            'deposit_terms' => DepositTerms::class,
            'auction_type' => AuctionType::class,
            'status' => ListingStatus::class,
            'seller_net_price' => 'integer',
            'seller_price' => 'integer',
            'renovation_cost' => 'integer',
            'fee_bps' => 'integer',
            'minimum_price' => 'integer',
            'deposit_amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('floor_plans')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    /** Responsive WebP sizes, generated in the queue (spec D1). */
    public function registerMediaConversions(?Media $media = null): void
    {
        foreach (['thumb' => 400, 'card' => 800, 'large' => 1600, 'hero' => 2400] as $name => $width) {
            $this->addMediaConversion($name)
                ->performOnCollections('photos', 'floor_plans')
                ->queued()
                ->fit(Fit::Max, $width, $width)
                ->format('webp')
                ->quality(82);
        }
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

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<ListingAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ListingAttributeValue::class);
    }

    /** @return HasMany<ListingDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ListingDocument::class);
    }

    /** @return HasMany<BidderApproval, $this> */
    public function bidderApprovals(): HasMany
    {
        return $this->hasMany(BidderApproval::class);
    }

    /** @return HasMany<ListingStatusChange, $this> */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ListingStatusChange::class)->latest('id');
    }

    /** @return HasMany<ListingComplianceItem, $this> */
    public function complianceItems(): HasMany
    {
        return $this->hasMany(ListingComplianceItem::class);
    }

    /**
     * @param  Builder<Listing>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->whereIn('status', [ListingStatus::Scheduled, ListingStatus::Live]);
    }

    /**
     * Listings shown on their own page: active plus finished auctions.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeShown(Builder $query): void
    {
        $query->whereIn('status', [ListingStatus::Scheduled, ListingStatus::Live, ListingStatus::Closed, ListingStatus::UnderOffer, ListingStatus::Sold]);
    }
}
