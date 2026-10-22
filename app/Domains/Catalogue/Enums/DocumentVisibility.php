<?php

namespace App\Domains\Catalogue\Enums;

/**
 * Who may open a document. Documents are never public.
 */
enum DocumentVisibility: string
{
    case Internal = 'internal';
    case Verified = 'verified';
    case ApprovedNda = 'approved_nda';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal only',
            self::Verified => 'Verified bidders',
            self::ApprovedNda => 'Approved bidders after NDA (data room)',
        };
    }
}
