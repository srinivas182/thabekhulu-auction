<?php

namespace App\Domains\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $deposit_id
 * @property int|null $refund_batch_id
 * @property int $amount
 * @property string|null $channel
 * @property string $reason
 * @property string $status
 * @property Carbon $due_on
 * @property string|null $provider_ref
 * @property string|null $failure
 * @property Carbon|null $paid_at
 * @property-read Deposit $deposit
 * @property-read RefundBatch|null $batch
 */
class Refund extends Model
{
    public const REASONS = ['unsuccessful' => 'Did not win', 'runner_up_released' => 'Runner-up hold ended', 'withdrawn' => 'Listing withdrawn', 'security_released' => 'Security deposit released after completion', 'default_excess' => 'Excess after a capped forfeiture', 'other' => 'Other'];

    public const STATUSES = ['scheduled' => 'Scheduled', 'batched' => 'In a batch', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'due_on' => 'date', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<Deposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    /** @return BelongsTo<RefundBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(RefundBatch::class, 'refund_batch_id');
    }
}
