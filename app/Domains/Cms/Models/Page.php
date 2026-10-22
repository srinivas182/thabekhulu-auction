<?php

namespace App\Domains\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
class Page extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = ['id'];

    /** Images used by image blocks. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('large')->queued()->fit(Fit::Max, 1600, 1600)->format('webp')->quality(82);
    }

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
