<?php

namespace App\Domains\Catalogue\Models;

use App\Domains\Catalogue\Enums\DocumentVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property bool $is_mandatory
 * @property DocumentVisibility $visibility
 * @property list<string> $accepted_formats
 * @property int $max_size_mb
 * @property int $sort_order
 */
class DocumentType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'visibility' => DocumentVisibility::class,
            'accepted_formats' => 'array',
            'max_size_mb' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
