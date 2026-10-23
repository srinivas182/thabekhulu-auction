<?php

namespace App\Domains\Quotes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $position
 * @property string $label
 * @property int $percent_bps
 * @property string|null $due
 */
class QuotePayment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['percent_bps' => 'integer'];
    }
}
