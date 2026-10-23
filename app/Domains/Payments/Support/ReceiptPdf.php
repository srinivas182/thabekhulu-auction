<?php

namespace App\Domains\Payments\Support;

use App\Domains\Payments\Models\Receipt;
use App\Domains\Quotes\Settings\QuotationSettings;
use Barryvdh\DomPDF\Facade\Pdf;

/** Branded A4 receipt, using the quotation letterhead details. */
final class ReceiptPdf
{
    public static function render(Receipt $receipt): string
    {
        $receipt->loadMissing(['deposit.listing', 'deposit.user']);

        return Pdf::loadView('pdf.receipt', [
            'receipt' => $receipt,
            'company' => app(QuotationSettings::class),
            'tz' => (string) config('platform.display_timezone'),
        ])->setPaper('a4')->output();
    }
}
