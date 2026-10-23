<?php

namespace App\Domains\Verification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One sanctions/PEP screening of a person: recorded by a KYC officer or,
 * once wired with sandbox access, by the routed provider.
 *
 * @property int $id
 * @property int $user_id
 * @property string $result
 * @property string $lists
 * @property string $provider
 * @property string|null $reference
 * @property string|null $note
 * @property int|null $screened_by
 * @property Carbon $screened_at
 * @property-read User|null $screener
 */
class Screening extends Model
{
    public const RESULTS = ['clear' => 'Clear', 'possible_match' => 'Possible match', 'confirmed_match' => 'Confirmed match'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['screened_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }
}
