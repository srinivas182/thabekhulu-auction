<?php

namespace App\Domains\Bidding\Models;

use App\Domains\Verification\Models\BidderApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A maximum set before bidding opens; placed by the bid engine when the listing goes live.
 *
 * @property int $id
 * @property int $listing_id
 * @property int $bidder_approval_id
 * @property int $amount
 * @property Carbon $set_at
 * @property Carbon|null $applied_at
 * @property string|null $result
 * @property-read BidderApproval $approval
 */
class AdvanceBid extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'encrypted', 'set_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    /** @return BelongsTo<BidderApproval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(BidderApproval::class, 'bidder_approval_id');
    }
}
