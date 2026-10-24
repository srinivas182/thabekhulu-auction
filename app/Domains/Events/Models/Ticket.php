<?php

namespace App\Domains\Events\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $ticket_order_id
 * @property int $event_id
 * @property string $code
 * @property string $token_hash
 * @property CarbonImmutable|null $checked_in_at
 * @property int|null $checked_in_by
 * @property-read Event $event
 * @property-read TicketOrder $order
 */
class Ticket extends Model
{
    protected $guarded = ['id', 'ulid'];

    protected $hidden = ['token_hash'];

    protected static function booted(): void
    {
        static::creating(fn (Ticket $t) => $t->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<TicketOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }
}
