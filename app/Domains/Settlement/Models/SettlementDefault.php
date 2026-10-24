<?php

namespace App\Domains\Settlement\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $settlement_case_id
 * @property string $reason
 * @property int $auction_cost
 * @property int $proposed_forfeit
 * @property bool $security_signoff
 * @property string $status
 * @property int $declared_by
 * @property CarbonImmutable $declared_at
 * @property int|null $confirmed_by
 * @property CarbonImmutable|null $confirmed_at
 */
class SettlementDefault extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['auction_cost' => 'integer', 'proposed_forfeit' => 'integer', 'security_signoff' => 'boolean', 'declared_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
