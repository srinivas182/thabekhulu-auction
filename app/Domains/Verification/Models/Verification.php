<?php

namespace App\Domains\Verification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A buyer's identity verification. The ID number is encrypted; a keyed hash
 * enforces one account per ID without storing the number in plain text.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $status
 * @property string|null $document_type
 * @property string|null $id_number
 * @property string|null $id_number_hash
 * @property string|null $passport_country
 * @property Carbon|null $date_of_birth
 * @property string|null $marital_status
 * @property array<string, mixed>|null $spouse
 * @property array<string, array<string, mixed>>|null $checks
 * @property string|null $rejection_reason
 * @property string|null $info_requested
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $expires_at
 * @property-read User $user
 */
class Verification extends Model
{
    public const STATUSES = ['in_progress' => 'In progress', 'submitted' => 'Submitted', 'verified' => 'Verified', 'review' => 'Being reviewed', 'rejected' => 'Not approved', 'expired' => 'Expired'];

    protected $guarded = ['id', 'ulid'];

    protected $hidden = ['id_number', 'id_number_hash'];

    protected static function booted(): void
    {
        static::creating(fn (Verification $v) => $v->ulid ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'id_number' => 'encrypted', 'spouse' => 'encrypted:array', 'checks' => 'array', 'date_of_birth' => 'date',
            'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'expires_at' => 'datetime', 'reviewed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public static function hashIdNumber(string $number): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $number) ?? '', (string) config('app.key'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<VerificationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }
}
