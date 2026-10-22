<?php

namespace App\Domains\Quotes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $quote_id
 * @property int $position
 * @property string $kind
 * @property string $frequency
 * @property string $description
 * @property int $quantity
 * @property string|null $unit
 * @property int $unit_price
 * @property string|null $discount_type
 * @property int $discount_value
 * @property bool $vat_applicable
 * @property int $net
 * @property int $vat
 */
class QuoteLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'integer', 'discount_value' => 'integer', 'vat_applicable' => 'boolean', 'net' => 'integer', 'vat' => 'integer'];
    }
}
