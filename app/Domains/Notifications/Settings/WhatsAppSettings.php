<?php

namespace App\Domains\Notifications\Settings;

use Spatie\LaravelSettings\Settings;

class WhatsAppSettings extends Settings
{
    public bool $enabled;

    public string $phone_number_id;

    public string $access_token;

    public string $api_version;

    public string $language;

    public string $template_outbid;

    public string $template_closing;

    public static function group(): string
    {
        return 'whatsapp';
    }

    /** @return list<string> */
    public static function encrypted(): array
    {
        return ['access_token'];
    }

    public function ready(): bool
    {
        return $this->enabled && $this->phone_number_id !== '' && $this->access_token !== '';
    }
}
