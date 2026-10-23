<?php

namespace App\Domains\Verification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $bidding_entity_id
 * @property int $amount
 * @property string $path
 * @property string $mime
 * @property string $status
 * @property Carbon|null $valid_until
 * @property string|null $note
 * @property-read User $user
 */
class ProofOfFunds extends Model
{
    public const VALID_DAYS = 90;

    protected $table = 'proofs_of_funds';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'valid_until' => 'date'];
    }

    public function isValidFor(int $amount): bool
    {
        return $this->status === 'accepted' && $this->valid_until !== null && ! $this->valid_until->endOfDay()->isPast() && $this->amount >= $amount;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
