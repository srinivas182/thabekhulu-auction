<?php

namespace App\Domains\Settlement\Models;

use App\Domains\Listings\Models\Listing;
use App\Domains\Verification\Models\BidderApproval;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $listing_id
 * @property int $bidder_approval_id
 * @property string $kind
 * @property int $price
 * @property string $status
 * @property CarbonImmutable|null $agreement_due
 * @property CarbonImmutable|null $balance_due
 * @property CarbonImmutable|null $offer_expires_at
 * @property CarbonImmutable|null $completed_at
 * @property-read Listing $listing
 * @property-read BidderApproval $approval
 */
class SettlementCase extends Model
{
    public const STEPS = ['agreement_signed' => 'Sale agreement signed', 'balance_paid' => 'Balance paid or guarantees delivered', 'attorney_appointed' => 'Transfer attorney appointed'];

    public const STATUSES = ['offered' => 'Offered to runner-up', 'open' => 'In progress', 'completed' => 'Completed', 'defaulted' => 'Defaulted', 'declined' => 'Offer declined', 'expired' => 'Offer expired'];

    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (SettlementCase $c) => $c->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['price' => 'integer', 'agreement_due' => 'date', 'balance_due' => 'date', 'offer_expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<BidderApproval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(BidderApproval::class, 'bidder_approval_id');
    }

    /** @return HasMany<SettlementStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(SettlementStep::class);
    }

    /** @return HasOne<SettlementDefault, $this> */
    public function default(): HasOne
    {
        return $this->hasOne(SettlementDefault::class);
    }

    /** Which deadline was missed, if any (a default may be declared only after one). */
    public function missedDeadline(): ?string
    {
        $done = $this->steps()->pluck('step')->all();
        $today = CarbonImmutable::today((string) config('platform.display_timezone'));
        if ($this->agreement_due && $this->agreement_due->lt($today) && ! in_array('agreement_signed', $done, true)) {
            return 'sale agreement not signed by '.$this->agreement_due->format('j F Y');
        }
        if ($this->balance_due && $this->balance_due->lt($today) && ! in_array('balance_paid', $done, true)) {
            return 'balance not paid by '.$this->balance_due->format('j F Y');
        }

        return null;
    }
}
