<?php

namespace App\Domains\Payments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property string $status
 * @property int $total
 * @property int $count
 * @property int $prepared_by
 * @property Carbon $prepared_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property string|null $proof_path
 * @property-read User $preparer
 * @property-read User|null $approver
 */
class RefundBatch extends Model
{
    public const STATUSES = ['prepared' => 'Waiting for approval', 'approved' => 'Approved: pay at the bank', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['total' => 'integer', 'count' => 'integer', 'prepared_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
