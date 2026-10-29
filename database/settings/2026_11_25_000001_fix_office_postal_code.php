<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Durban city centre postal code is 4001. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('general.office_address', fn () => "Thabekhulu Development Group (Pty) Ltd\n6th Floor, Royal Towers\n30 Dorothy Nyembe Street\nDurban, 4001");
    }
};
