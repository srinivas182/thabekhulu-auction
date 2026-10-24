<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Report-only until a super admin records the attorney's approval of the schedule (H6).
        $this->migrator->add('privacy.purge_enabled', false);
        $this->migrator->add('privacy.approval_reference', '');
        $this->migrator->add('privacy.approved_by', '');
        $this->migrator->add('privacy.approved_at', '');
    }
};
