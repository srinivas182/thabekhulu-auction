<?php

namespace App\Domains\Listings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $listing_id
 * @property int $category_attribute_id
 * @property string|null $value
 */
class ListingAttributeValue extends Model
{
    protected $guarded = ['id'];
}
