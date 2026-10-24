<?php

namespace App\Domains\Settlement\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $settlement_case_id
 * @property string $step
 * @property CarbonImmutable $done_on
 * @property string|null $document_path
 * @property string|null $document_mime
 * @property string|null $note
 * @property int $recorded_by
 */
class SettlementStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['done_on' => 'date'];
    }
}
