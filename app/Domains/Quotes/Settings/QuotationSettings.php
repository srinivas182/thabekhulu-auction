<?php

namespace App\Domains\Quotes\Settings;

use Spatie\LaravelSettings\Settings;

class QuotationSettings extends Settings
{
    public string $company_name;

    public string $registration_number;

    public string $vat_number;

    public string $address;

    public string $bank_details;

    /** Basis points: 1500 = 15%. */
    public int $vat_rate_bps;

    public int $validity_days;

    /** Rand: quotes with a once-off total above this need approval. */
    public int $approval_threshold;

    public int $reminder_days;

    public string $number_prefix;

    public static function group(): string
    {
        return 'quotations';
    }
}
