<?php

namespace App\Domains\Events\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $kind
 * @property string|null $venue
 * @property string|null $online_link
 * @property int|null $listing_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $price
 * @property int $capacity
 * @property string $status
 */
class Event extends Model
{
    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (Event $e) => $e->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'price' => 'integer', 'capacity' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Seats taken: paid and free tickets, plus pending card payments still within their hold. */
    public function seatsTaken(): int
    {
        return (int) TicketOrder::where('event_id', $this->id)
            ->where(fn ($q) => $q->whereIn('status', ['paid', 'free'])->orWhere(fn ($p) => $p->where('status', 'pending')->where('hold_until', '>', now())))
            ->sum('quantity');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
