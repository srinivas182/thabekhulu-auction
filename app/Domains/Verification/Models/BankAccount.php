<?php

namespace App\Domains\Verification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The account deposits are refunded to. It must be in the bidder's own
 * name, and cannot be changed while a refund or deposit is pending.
 *
 * @property int $id
 * @property int $user_id
 * @property string $bank
 * @property string $branch_code
 * @property string $account_number
 * @property string $account_last4
 * @property string $account_type
 * @property string $holder_name
 * @property string $status
 * @property string|null $proof_path
 * @property string|null $rejection_reason
 * @property bool $locked
 * @property Carbon|null $verified_at
 * @property-read User $user
 */
class BankAccount extends Model
{
    /** Major South African banks with their universal branch codes. */
    public const BANKS = [
        'ABSA' => '632005', 'African Bank' => '430000', 'Bidvest Bank' => '462005', 'Capitec' => '470010', 'Discovery Bank' => '679000',
        'FNB' => '250655', 'Investec' => '580105', 'Nedbank' => '198765', 'Standard Bank' => '051001', 'TymeBank' => '678910', 'Other' => '',
    ];

    public const TYPES = ['cheque' => 'Cheque / current', 'savings' => 'Savings', 'transmission' => 'Transmission'];

    protected $guarded = ['id'];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'locked' => 'boolean', 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
