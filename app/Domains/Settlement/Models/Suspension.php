<?php

namespace App\Domains\Settlement\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Bidding suspension after a default (PRD: 12 months, setting).
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $until
 * @property string $reason
 * @property CarbonImmutable|null $lifted_at
 */
class Suspension extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['until' => 'date', 'lifted_at' => 'datetime'];
    }

    public static function activeFor(int $userId): ?self
    {
        return self::where('user_id', $userId)->whereNull('lifted_at')->whereDate('until', '>=', CarbonImmutable::today())->latest('until')->first();
    }
}
