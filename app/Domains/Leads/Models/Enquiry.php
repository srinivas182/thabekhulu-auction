<?php

namespace App\Domains\Leads\Models;

use App\Domains\Listings\Models\Listing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $subject
 * @property string $message
 * @property int|null $listing_id
 * @property string $status
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read Listing|null $listing
 */
class Enquiry extends Model
{
    protected $guarded = ['id'];

    /** @return HasMany<EnquiryReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(EnquiryReply::class)->oldest('id');
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
