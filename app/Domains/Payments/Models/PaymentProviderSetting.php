<?php

namespace App\Domains\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property bool $enabled
 * @property string $mode
 * @property array<string, string>|null $credentials
 * @property Carbon|null $last_success_at
 * @property string|null $last_error
 */
class PaymentProviderSetting extends Model
{
    protected $table = 'payment_providers';

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'credentials' => 'encrypted:array', 'last_success_at' => 'datetime'];
    }
}
