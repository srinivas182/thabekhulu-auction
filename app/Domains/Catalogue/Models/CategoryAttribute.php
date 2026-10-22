<?php

namespace App\Domains\Catalogue\Models;

use App\Domains\Catalogue\Enums\AttributeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $key
 * @property string $label
 * @property AttributeType $type
 * @property string|null $unit
 * @property list<string>|null $options
 * @property bool $is_required
 * @property bool $show_on_card
 * @property bool $show_in_key_facts
 * @property bool $is_filterable
 * @property int $sort_order
 */
class CategoryAttribute extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'show_on_card' => 'boolean',
            'show_in_key_facts' => 'boolean',
            'is_filterable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
