<?php

namespace App\Domains\Listings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $listing_id
 * @property string|null $from
 * @property string $to
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class ListingStatusChange extends Model
{
    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
