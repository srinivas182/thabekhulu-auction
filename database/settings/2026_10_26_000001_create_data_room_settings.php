<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('dataroom.nda_text', '<p><strong>[Non-disclosure agreement to be supplied by Thabekhulu’s attorney.]</strong></p><p>By accepting, you agree to keep the documents in this data room confidential, to use them only to evaluate this property, and not to share them with anyone except your professional advisers who are bound by the same duty.</p>');
        $this->migrator->add('dataroom.nda_version', 1);
        $this->migrator->add('dataroom.daily_download_alert', 20);
    }
};
