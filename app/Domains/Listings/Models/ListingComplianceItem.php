<?php

namespace App\Domains\Listings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $listing_id
 * @property string $item
 * @property int $signed_off_by
 * @property Carbon $signed_off_at
 * @property string|null $note
 * @property-read User $signer
 */
class ListingComplianceItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['signed_off_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_off_by');
    }
}
