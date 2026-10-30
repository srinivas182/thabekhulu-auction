<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Security approval codes for deletes, public-listing changes and finance approvals, sent to these approvers. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('security.approvals_enabled', true);
        $this->migrator->add('security.approver_emails', ['srinivas@mayuraconsultancy.com', 'admin@stevemaqueen.co.za']);
    }
};
