<?php

namespace App\Domains\Bidding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only: a bid is never edited or deleted; only voiding (B9) sets the
 * voided_* columns, once.
 *
 * @property int $id
 * @property int $listing_id
 * @property int $bidder_approval_id
 * @property string $bidder_number
 * @property int $amount
 * @property string $kind
 * @property Carbon $placed_at
 * @property string|null $ip_address
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 */
class Bid extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Bid $bid) {
            $changed = array_keys($bid->getDirty());
            if (array_diff($changed, ['voided_at', 'voided_by', 'void_reason']) !== [] || $bid->getOriginal('voided_at') !== null) {
                throw new LogicException('Bids cannot be changed; only voided once.');
            }
        });
        static::deleting(fn () => throw new LogicException('Bids cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'placed_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
