<?php

namespace App\Domains\Settings\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    /** Always true: staff two-factor is mandatory and cannot be switched off. */
    public bool $staff_two_factor_required;

    public bool $email_verification_required;

    public int $session_lifetime_minutes;

    /**
     * IP addresses or CIDR ranges allowed to use the back office.
     * Empty means no restriction.
     *
     * @var list<string>
     */
    public array $admin_ip_allowlist;

    /** Sensitive actions need a code emailed to the approvers. */
    public bool $approvals_enabled;

    /** @var list<string> */
    public array $approver_emails;

    public static function group(): string
    {
        return 'security';
    }
}
