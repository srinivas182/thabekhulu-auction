<?php

namespace App\Domains\Settlement\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $number
 * @property int $settlement_case_id
 * @property string $recipient
 * @property string|null $recipient_address
 * @property string|null $recipient_vat_number
 * @property string $description
 * @property int $fee
 * @property int $vat_rate_bps
 * @property int $vat
 * @property int $total
 * @property CarbonImmutable $issued_at
 * @property-read SettlementCase $case
 */
class Invoice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fee' => 'integer', 'vat' => 'integer', 'total' => 'integer', 'vat_rate_bps' => 'integer', 'issued_at' => 'datetime'];
    }

    /** @return BelongsTo<SettlementCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SettlementCase::class, 'settlement_case_id');
    }
}
