<?php

namespace App\Domains\Events\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property string $reference
 * @property string $name
 * @property string $email
 * @property string|null $mobile
 * @property int $quantity
 * @property int $amount
 * @property string $status
 * @property CarbonImmutable|null $hold_until
 * @property string|null $provider_ref
 * @property CarbonImmutable|null $paid_at
 * @property-read Event $event
 */
class TicketOrder extends Model
{
    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (TicketOrder $o) => $o->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'integer', 'hold_until' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
