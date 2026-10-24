<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('whatsapp.enabled', false);
        $this->migrator->add('whatsapp.phone_number_id', '');
        $this->migrator->addEncrypted('whatsapp.access_token', '');
        $this->migrator->add('whatsapp.api_version', 'v21.0');
        $this->migrator->add('whatsapp.language', 'en');
        // Template names as approved in Meta's WhatsApp Manager (utility category).
        $this->migrator->add('whatsapp.template_outbid', 'auction_outbid');
        $this->migrator->add('whatsapp.template_closing', 'auction_closing_soon');
    }
};
