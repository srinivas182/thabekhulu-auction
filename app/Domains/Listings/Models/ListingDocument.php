<?php

namespace App\Domains\Listings\Models;

use App\Domains\Catalogue\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $listing_id
 * @property int $document_type_id
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property-read DocumentType $documentType
 */
class ListingDocument extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
