<?php

namespace App\Domains\Theme\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope
 * @property string $status
 * @property array<string, mixed> $values
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $published_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property-read User|null $publisher
 */
class ThemeVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['values' => 'array', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
