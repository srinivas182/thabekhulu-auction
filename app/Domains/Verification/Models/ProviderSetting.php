<?php

namespace App\Domains\Verification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Super-admin settings for one provider. Credentials are encrypted at rest
 * with the application key (decision K2) and never sent to the browser.
 *
 * @property int $id
 * @property string $key
 * @property bool $enabled
 * @property string $mode
 * @property array<string, string>|null $credentials
 * @property Carbon|null $last_success_at
 * @property string|null $last_error
 */
class ProviderSetting extends Model
{
    protected $table = 'verification_providers';

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'credentials' => 'encrypted:array', 'last_success_at' => 'datetime'];
    }
}
