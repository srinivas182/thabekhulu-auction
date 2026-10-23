<?php

namespace App\Domains\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bank_import_id
 * @property string $line_hash
 * @property Carbon $posted_on
 * @property int $amount
 * @property string $description
 * @property string|null $reference_found
 * @property int|null $deposit_id
 * @property string $status
 * @property string|null $resolution
 * @property-read Deposit|null $deposit
 */
class BankLine extends Model
{
    public const STATUSES = ['matched' => 'Matched', 'unmatched' => 'No matching reference', 'short' => 'Less than the deposit', 'over' => 'More than the deposit', 'resolved' => 'Resolved', 'ignored' => 'Not a deposit'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['posted_on' => 'date', 'amount' => 'integer'];
    }

    /** @return BelongsTo<Deposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }
}
