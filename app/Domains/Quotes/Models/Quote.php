<?php

namespace App\Domains\Quotes\Models;

use App\Domains\Listings\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $number
 * @property int $version
 * @property string $status
 * @property string $title
 * @property int $client_id
 * @property int|null $listing_id
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon $issue_date
 * @property Carbon $valid_until
 * @property int $vat_rate_bps
 * @property string|null $payment_method
 * @property string|null $scope
 * @property string|null $assumptions
 * @property string|null $exclusions
 * @property string|null $terms
 * @property string|null $internal_notes
 * @property int $once_subtotal
 * @property int $once_discount
 * @property int $once_vat
 * @property int $once_total
 * @property int $monthly_total
 * @property int $annual_total
 * @property string|null $fingerprint
 * @property string|null $accepted_name
 * @property Carbon|null $accepted_at
 * @property string|null $accepted_ip
 * @property string|null $accepted_agent
 * @property string|null $decline_reason
 * @property Carbon|null $sent_at
 * @property Carbon|null $updated_at
 * @property-read Client $client
 * @property-read Listing|null $listing
 * @property-read User|null $creator
 */
class Quote extends Model
{
    public const STATUSES = [
        'draft' => 'Draft', 'awaiting_approval' => 'Awaiting approval', 'approved' => 'Approved', 'sent' => 'Sent',
        'viewed' => 'Viewed', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired', 'superseded' => 'Superseded',
    ];

    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (Quote $q) => $q->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'valid_until' => 'date', 'accepted_at' => 'datetime', 'sent_at' => 'datetime',
            'vat_rate_bps' => 'integer', 'once_subtotal' => 'integer', 'once_discount' => 'integer', 'once_vat' => 'integer',
            'once_total' => 'integer', 'monthly_total' => 'integer', 'annual_total' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function reference(): string
    {
        return $this->version > 1 ? "{$this->number}-v{$this->version}" : $this->number;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'awaiting_approval', 'approved'], true);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<QuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    /** @return HasMany<QuoteMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(QuoteMilestone::class)->orderBy('position');
    }

    /** @return HasMany<QuotePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(QuotePayment::class)->orderBy('position');
    }

    /** @return HasMany<QuoteEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(QuoteEvent::class)->latest('id');
    }
}
