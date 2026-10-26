<?php

namespace App\Domains\Cms\Models;

use App\Domains\Catalogue\Models\Category;
use App\Domains\Catalogue\Models\CategoryAttribute;
use App\Domains\Listings\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rule-based group of listings shown on the home page with a live count,
 * e.g. "Coal mining" = category Mining + commodity Coal.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $category_attribute_id
 * @property string|null $attribute_value
 * @property bool $renovated_only
 * @property bool $is_visible
 * @property int $sort_order
 * @property-read Category|null $category
 * @property-read CategoryAttribute|null $attribute
 */
class Collection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['renovated_only' => 'boolean', 'is_visible' => 'boolean'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<CategoryAttribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class, 'category_attribute_id');
    }

    /**
     * Public listings matching this collection's rule.
     *
     * @return Builder<Listing>
     */
    public function listings(): Builder
    {
        return Listing::query()->public()
            ->when($this->category_id, fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($this->renovated_only, fn (Builder $q) => $q->where('deal_type', 'renovated')) // same rule as the badge and the filter
            ->when($this->category_attribute_id && filled($this->attribute_value), fn (Builder $q) => $q->whereHas('attributeValues', fn (Builder $v) => $v
                ->where('category_attribute_id', $this->category_attribute_id)
                ->where('value', $this->attribute_value)));
    }
}
