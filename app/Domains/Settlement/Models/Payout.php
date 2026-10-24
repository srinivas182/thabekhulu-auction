<?php

namespace App\Domains\Settlement\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $settlement_case_id
 * @property string $deal_type
 * @property int $price
 * @property int $fee
 * @property int $vat
 * @property int $amount
 * @property string $payee
 * @property string|null $payee_reference
 * @property string $status
 * @property int $prepared_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $paid_at
 * @property string|null $proof_path
 * @property-read SettlementCase $case
 */
class Payout extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'fee' => 'integer', 'vat' => 'integer', 'amount' => 'integer', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<SettlementCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SettlementCase::class, 'settlement_case_id');
    }
}
