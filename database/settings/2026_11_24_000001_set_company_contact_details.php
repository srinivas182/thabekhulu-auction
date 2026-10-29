<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Thabekhulu's contact details, as supplied for launch. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('general.contact_email', fn () => 'admin@stevemaqueens.co.za');
        $this->migrator->update('general.contact_phone', fn () => '+27 61 961 1898');
        $this->migrator->update('general.office_address', fn () => "Thabekhulu Development Group (Pty) Ltd\n6th Floor, Royal Towers\n30 Dorothy Nyembe Street\nDurban, 4100");
    }
};
