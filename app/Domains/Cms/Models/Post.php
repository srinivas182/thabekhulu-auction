<?php

namespace App\Domains\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An Investor insights article. Visible when published and its publish
 * date has passed (a future date schedules it).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $topic
 * @property string|null $excerpt
 * @property string|null $body
 * @property int|null $author_id
 * @property string $status
 * @property Carbon|null $published_at
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 */
class Post extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const TOPICS = ['Market insights', 'Mining', 'Buying at auction', 'Selling', 'Auction results', 'News'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')->queued()->fit(Fit::Max, 800, 800)->format('webp')->quality(82);
        $this->addMediaConversion('large')->queued()->fit(Fit::Max, 1600, 1600)->format('webp')->quality(82);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
