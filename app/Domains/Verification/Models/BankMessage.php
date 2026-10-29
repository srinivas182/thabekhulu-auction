<?php

namespace App\Domains\Verification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in a buyer's refund bank account conversation.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $bank_account_id
 * @property int|null $author_id
 * @property string $author
 * @property string $kind
 * @property string|null $body
 * @property Carbon $created_at
 */
class BankMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public static function note(int $buyerId, ?int $bankId, string $author, string $kind, ?string $body = null, ?int $authorId = null): void
    {
        self::create(['user_id' => $buyerId, 'bank_account_id' => $bankId, 'author' => $author, 'kind' => $kind, 'body' => $body, 'author_id' => $authorId, 'created_at' => now()]);
    }

    /** @return array<int, array{author: string, kind: string, body: string|null, by: string|null, at: string}> */
    public static function thread(int $buyerId): array
    {
        return self::with('authorUser')->where('user_id', $buyerId)->orderBy('id')->get()->map(fn (self $m): array => [
            'author' => $m->author, 'kind' => $m->kind, 'body' => $m->body, 'by' => $m->authorUser?->name,
            'at' => $m->created_at->setTimezone((string) config('platform.display_timezone'))->format('j M Y, H:i'),
        ])->values()->all();
    }
}
