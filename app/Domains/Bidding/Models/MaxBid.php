<?php

namespace App\Domains\Bidding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A bidder's private maximum (B3), encrypted; never shown to staff during the auction.
 *
 * @property int $id
 * @property int $listing_id
 * @property int $bidder_approval_id
 * @property string $amount
 * @property Carbon $set_at
 * @property Carbon|null $voided_at
 */
class MaxBid extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['amount'];

    protected function casts(): array
    {
        return ['amount' => 'encrypted', 'set_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function value(): int
    {
        return (int) $this->amount;
    }
}
