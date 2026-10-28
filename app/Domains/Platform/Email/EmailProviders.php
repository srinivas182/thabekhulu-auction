<?php

namespace App\Domains\Platform\Email;

/**
 * Providers offered in the back office. Every one accepts SMTP with a key or
 * password, so one tested sending path serves all of them. Host and port are
 * filled in for the admin; they can still be edited.
 */
final class EmailProviders
{
    /** @return array<string, array{label: string, host: string, port: int, username: string, password: string, help: string}> */
    public static function all(): array
    {
        return [
            'brevo' => ['label' => 'Brevo', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'username' => 'SMTP login (shown in Brevo under SMTP & API)', 'password' => 'SMTP key',
                'help' => 'In Brevo: SMTP & API » SMTP, copy the login and generate an SMTP key. Verify your sender domain in Brevo first.'],
            'sendgrid' => ['label' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'username' => 'apikey (type exactly this)', 'password' => 'API key',
                'help' => 'In SendGrid: Settings » API Keys, create a key with Mail Send access. The username is the word apikey.'],
            'mailgun' => ['label' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'username' => 'SMTP login (for example postmaster@mg.yourdomain)', 'password' => 'SMTP password',
                'help' => 'In Mailgun: Sending » Domain settings » SMTP credentials. For an EU account use smtp.eu.mailgun.org.'],
            'ses' => ['label' => 'Amazon SES', 'host' => 'email-smtp.af-south-1.amazonaws.com', 'port' => 587, 'username' => 'SMTP username', 'password' => 'SMTP password',
                'help' => 'In Amazon SES: SMTP settings » Create SMTP credentials. Change the region in the server name if your account isn’t in Cape Town (af-south-1).'],
            'postmark' => ['label' => 'Postmark', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'username' => 'Server API token', 'password' => 'Server API token (again)',
                'help' => 'In Postmark: your server » API Tokens. The same token is the username and the password.'],
            'microsoft365' => ['label' => 'Microsoft 365', 'host' => 'smtp.office365.com', 'port' => 587, 'username' => 'Mailbox address', 'password' => 'Mailbox or app password',
                'help' => 'SMTP sending must be allowed for this mailbox in the Microsoft 365 admin centre.'],
            'google' => ['label' => 'Google Workspace', 'host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'Google Workspace address', 'password' => 'App password',
                'help' => 'Create an app password in the Google account’s security settings (two-step verification must be on).'],
            'smtp' => ['label' => 'Other SMTP server', 'host' => '', 'port' => 587, 'username' => 'Username', 'password' => 'Password',
                'help' => 'Any SMTP server, including a cPanel mailbox: use the outgoing server shown under Email Accounts » Connect Devices.'],
        ];
    }
}
