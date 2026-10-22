<?php

namespace App\Domains\Catalogue\Models;

use App\Domains\Catalogue\Enums\DepositTerms;
use App\Domains\Listings\Models\Listing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property int $default_deposit
 * @property int $default_fee_bps
 * @property DepositTerms $default_deposit_terms
 * @property bool $requires_compliance_notice
 * @property bool $is_visible
 * @property int $sort_order
 * @property string|null $seo_title
 * @property string|null $seo_description
 */
class Category extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'default_deposit' => 'integer',
            'default_fee_bps' => 'integer',
            'default_deposit_terms' => DepositTerms::class,
            'requires_compliance_notice' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<CategoryAttribute, $this> */
    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class)->orderBy('sort_order');
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** @return HasMany<DocumentType, $this> */
    public function documentTypes(): HasMany
    {
        return $this->hasMany(DocumentType::class)->orderBy('sort_order');
    }
}
