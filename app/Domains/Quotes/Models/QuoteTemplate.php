<?php

namespace App\Domains\Quotes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property array<string, mixed> $content
 * @property bool $needs_review
 */
class QuoteTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content' => 'array', 'needs_review' => 'boolean'];
    }
}
