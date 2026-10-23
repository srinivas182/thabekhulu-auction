<?php

namespace App\Domains\Verification\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $bidding_entity_id
 * @property string $kind
 * @property string $path
 * @property string $mime
 */
class EntityDocument extends Model
{
    protected $guarded = ['id'];
}
