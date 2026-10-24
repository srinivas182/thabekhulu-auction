<?php

namespace App\Domains\Sellers\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $price
 * @property int $duration_days
 * @property int $max_listings
 * @property bool $active
 */
class SellerPackage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'duration_days' => 'integer', 'max_listings' => 'integer', 'active' => 'boolean'];
    }
}
