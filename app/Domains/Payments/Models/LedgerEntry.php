<?php

namespace App\Domains\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One movement of money (decision P2). Entries are never changed or
 * deleted: corrections are new entries. Balances are always calculated.
 *
 * @property int $id
 * @property int $deposit_id
 * @property string $type
 * @property int $amount
 * @property string $source
 * @property string|null $source_ref
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries cannot be changed. Add a correcting entry instead.'));
        static::deleting(fn () => throw new LogicException('Ledger entries cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'created_at' => 'datetime'];
    }
}
