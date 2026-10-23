<?php

namespace App\Domains\Verification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A company or trust a buyer bids for. Directors/trustees and owners of 25%
 * or more are kept encrypted.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $type
 * @property string $name
 * @property string $registration_number
 * @property list<array{name: string, role: string, id_number: string, ownership: float}> $controllers
 * @property string $status
 * @property string|null $rejection_reason
 * @property-read User $user
 */
class BiddingEntity extends Model
{
    public const DOCUMENTS = [
        'company' => ['registration' => 'CIPC registration certificate (CoR14.3)', 'resolution' => 'Board resolution authorising you to bid', 'ownership' => 'Share register or ownership structure'],
        'trust' => ['trust_deed' => 'Trust deed', 'letters_of_authority' => 'Letters of authority from the Master', 'resolution' => 'Trustees’ resolution authorising you to bid'],
    ];

    protected $guarded = ['id', 'ulid'];

    protected static function booted(): void
    {
        static::creating(fn (BiddingEntity $e) => $e->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['controllers' => 'encrypted:array'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EntityDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(EntityDocument::class);
    }
}
