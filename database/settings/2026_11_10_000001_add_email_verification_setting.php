<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // On by default; may be switched off only for testing (security:check flags it).
        $this->migrator->add('security.email_verification_required', true);
    }
};
