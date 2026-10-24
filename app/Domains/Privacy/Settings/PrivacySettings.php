<?php

namespace App\Domains\Privacy\Settings;

use Spatie\LaravelSettings\Settings;

class PrivacySettings extends Settings
{
    public bool $purge_enabled;

    public string $approval_reference;

    public string $approved_by;

    public string $approved_at;

    public static function group(): string
    {
        return 'privacy';
    }
}
