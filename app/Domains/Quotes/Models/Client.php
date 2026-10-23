<?php

namespace App\Domains\Quotes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $company
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $vat_number
 * @property string|null $notes
 * @property int|null $user_id
 */
class Client extends Model
{
    protected $guarded = ['id'];

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
