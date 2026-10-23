<?php

namespace App\Domains\Quotes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quote_id
 * @property string $type
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $note
 * @property Carbon $created_at
 */
class QuoteEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
