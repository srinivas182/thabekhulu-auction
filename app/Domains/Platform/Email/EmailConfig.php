<?php

namespace App\Domains\Platform\Email;

/**
 * Applies the Email provider settings to Laravel's mail configuration on every
 * request and in the queue worker. When email isn't enabled (or isn't
 * complete), mail goes to the "array" mailer: nothing is sent, nothing fails.
 */
final class EmailConfig
{
    public static function apply(): void
    {
        try {
            $s = app(EmailSettings::class);
            $ready = $s->ready(); // settings load lazily: reading them is what fails before they're migrated
        } catch (\Throwable) {
            return; // first deploy: settings not migrated yet, so leave the configuration alone (migrate must still run)
        }

        if (! $ready) {
            config(['mail.default' => 'array']);

            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $s->port === 465 ? 'smtps' : 'smtp', // 587 upgrades with STARTTLS
            'mail.mailers.smtp.host' => $s->host,
            'mail.mailers.smtp.port' => $s->port,
            'mail.mailers.smtp.username' => $s->username,
            'mail.mailers.smtp.password' => $s->password,
            'mail.from.address' => $s->from_address,
            'mail.from.name' => $s->from_name,
        ]);
    }

    public static function sending(): bool
    {
        try {
            return app(EmailSettings::class)->ready();
        } catch (\Throwable) {
            return false;
        }
    }
}
