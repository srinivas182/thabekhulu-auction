<?php

namespace App\Domains\Quotes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $unit
 * @property int $unit_price
 * @property bool $vat_applicable
 * @property string $frequency
 * @property bool $is_active
 */
class CatalogueItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'vat_applicable' => 'boolean', 'is_active' => 'boolean'];
    }
}
