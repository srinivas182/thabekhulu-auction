<?php

namespace App\Domains\Payments\Models;

use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $listing_id
 * @property int $user_id
 * @property int $bidder_approval_id
 * @property int $amount
 * @property string $reference
 * @property string $method
 * @property string|null $proof_path
 * @property string|null $proof_mime
 * @property Carbon|null $proof_uploaded_at
 * @property string|null $provider_ref
 * @property string $status
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 * @property-read Listing $listing
 * @property-read User $user
 * @property-read BidderApproval $approval
 */
class Deposit extends Model
{
    public const STATUSES = [
        'awaiting' => 'Awaiting payment', 'part_paid' => 'Part paid', 'received' => 'Received', 'refund_scheduled' => 'Refund scheduled',
        'refunded' => 'Refunded', 'applied' => 'Applied to the price', 'forfeited' => 'Forfeited after default',
    ];

    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (Deposit $d) => $d->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'received_at' => 'datetime', 'proof_uploaded_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Money held now: the sum of every ledger entry. */
    public function balance(): int
    {
        return (int) $this->entries()->sum('amount');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderBy('id');
    }

    /** @return HasMany<Receipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
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

    /** @return BelongsTo<BidderApproval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(BidderApproval::class, 'bidder_approval_id');
    }
}
