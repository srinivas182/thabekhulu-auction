<?php

namespace App\Domains\Bidding\Models;

use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $listing_id
 * @property int|null $current_amount
 * @property int|null $leader_approval_id
 * @property CarbonImmutable $ends_at
 * @property int $extensions
 * @property int $bid_count
 * @property CarbonImmutable|null $paused_at
 * @property-read Listing $listing
 * @property-read BidderApproval|null $leader
 */
class AuctionState extends Model
{
    protected $primaryKey = 'listing_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ends_at' => 'datetime', 'paused_at' => 'datetime', 'current_amount' => 'integer'];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<BidderApproval, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(BidderApproval::class, 'leader_approval_id');
    }
}
