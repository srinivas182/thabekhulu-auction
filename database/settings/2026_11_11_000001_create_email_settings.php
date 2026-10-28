<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Off until an admin enables a provider: no emails are sent until then.
        $this->migrator->add('email.enabled', false);
        $this->migrator->add('email.provider', 'brevo');
        $this->migrator->add('email.host', 'smtp-relay.brevo.com');
        $this->migrator->add('email.port', 587);
        $this->migrator->add('email.username', '');
        $this->migrator->addEncrypted('email.password', '');
        $this->migrator->add('email.from_address', '');
        $this->migrator->add('email.from_name', 'Thabekhulu Development Group');
    }
};
