<?php

namespace App\Domains\Leads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A "Sell with us" submission. Photos are stored on the private disk and
 * only staff with leads.view can open them.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property string $mobile
 * @property string $property_type
 * @property string $location
 * @property int|null $asking_price
 * @property string $description
 * @property string $status
 * @property int|null $assigned_to
 * @property Carbon $consent_at
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read User|null $assignee
 */
class Lead extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'valuation_booked' => 'Valuation booked', 'listed' => 'Listed', 'not_suitable' => 'Not suitable'];

    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (Lead $lead) => $lead->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['asking_price' => 'integer', 'consent_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk('local')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /** @return HasMany<LeadNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest('id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
