<?php

namespace App\Domains\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property list<array<string, mixed>> $blocks
 * @property bool $is_system
 * @property bool $needs_content
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $updated_by
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 */
class Page extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_system' => 'boolean',
            'needs_content' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
