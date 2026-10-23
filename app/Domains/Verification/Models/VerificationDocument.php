<?php

namespace App\Domains\Verification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $verification_id
 * @property string $kind
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property Carbon|null $delete_after
 */
class VerificationDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['delete_after' => 'date'];
    }
}
