<?php

namespace App\Domains\Settlement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in a settlement conversation.
 *
 * @property int $id
 * @property int $settlement_case_id
 * @property int|null $settlement_upload_id
 * @property int|null $user_id
 * @property string $author
 * @property string $kind
 * @property string|null $body
 * @property Carbon $created_at
 */
class SettlementMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{author: string, kind: string, body: string|null, by: string|null, at: string, upload: int|null} */
    public function present(): array
    {
        return ['author' => $this->author, 'kind' => $this->kind, 'body' => $this->body, 'by' => $this->user?->name, 'upload' => $this->settlement_upload_id,
            'at' => $this->created_at->setTimezone((string) config('platform.display_timezone'))->format('j M Y, H:i')];
    }
}
