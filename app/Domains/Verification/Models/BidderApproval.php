<?php

namespace App\Domains\Verification\Models;

use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Permission for one buyer to bid on one listing (decision K6).
 *
 * @property int $id
 * @property int $listing_id
 * @property int $user_id
 * @property int|null $bidding_entity_id
 * @property int|null $proof_of_funds_id
 * @property string $status
 * @property string|null $bidder_number
 * @property list<array<string, mixed>>|null $checklist
 * @property string|null $reason
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property-read Listing $listing
 * @property-read User $user
 * @property-read BiddingEntity|null $entity
 * @property-read ProofOfFunds|null $proofOfFunds
 */
class BidderApproval extends Model
{
    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Approved to bid', 'rejected' => 'Not approved', 'revoked' => 'Approval withdrawn'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<BiddingEntity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(BiddingEntity::class, 'bidding_entity_id');
    }

    /** @return BelongsTo<ProofOfFunds, $this> */
    public function proofOfFunds(): BelongsTo
    {
        return $this->belongsTo(ProofOfFunds::class, 'proof_of_funds_id');
    }
}
