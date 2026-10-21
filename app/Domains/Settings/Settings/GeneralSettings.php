<?php

namespace App\Domains\Settings\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;

    public string $contact_email;

    public string $contact_phone;

    public string $office_address;

    /** Fixed at ZAR in Phase 1; other currencies arrive in Phase 2. */
    public string $currency;

    public static function group(): string
    {
        return 'general';
    }
}
