<?php

namespace App\Domains\Settlement\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The winner's balance, paid to Thabekhulu (amounts in cents).
 *
 * @property int $id
 * @property string $ulid
 * @property int $settlement_case_id
 * @property string $reference
 * @property int $amount_due
 * @property int $received
 * @property string $status
 * @property string|null $proof_path
 * @property string|null $proof_mime
 * @property CarbonInterface|null $proof_uploaded_at
 * @property CarbonInterface|null $received_at
 * @property-read SettlementCase $case
 */
class SettlementPayment extends Model
{
    public const STATUSES = ['awaiting' => 'Awaiting payment', 'part_paid' => 'Part paid', 'received' => 'Received'];

    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (SettlementPayment $p) => $p->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['proof_uploaded_at' => 'datetime', 'received_at' => 'datetime'];
    }

    /** @return BelongsTo<SettlementCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SettlementCase::class, 'settlement_case_id');
    }

    public function outstanding(): int
    {
        return max(0, $this->amount_due - $this->received);
    }
}
