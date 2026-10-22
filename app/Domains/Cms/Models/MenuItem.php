<?php

namespace App\Domains\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $menu
 * @property string $label
 * @property string $url
 * @property bool $new_tab
 * @property int $sort_order
 */
class MenuItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['new_tab' => 'boolean'];
    }
}
