<?php

namespace App\Domains\Settings\Support;

use App\Domains\Settings\Settings\AuctionSettings;
use App\Domains\Settings\Settings\GeneralSettings;
use App\Domains\Settings\Settings\SecuritySettings;
use Spatie\LaravelSettings\Settings;

/**
 * Describes each settings group for the admin screen: the class, its
 * fields, how each field is shown and validated. Adding a group in a later
 * sprint means adding an entry here; the controller and page are generic.
 *
 * Field types: text, email, tel, textarea, number, boolean, list.
 */
final class SettingsRegistry
{
    /**
     * @return array<string, array{class: class-string<Settings>, label: string, description: string, fields: array<string, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'class' => GeneralSettings::class,
                'label' => 'General',
                'description' => 'Business details shown on the website, emails and invoices.',
                'fields' => [
                    'site_name' => ['type' => 'text', 'label' => 'Business name', 'rules' => ['required', 'string', 'max:120']],
                    'contact_email' => ['type' => 'email', 'label' => 'Contact email', 'rules' => ['required', 'email', 'max:255']],
                    'contact_phone' => ['type' => 'tel', 'label' => 'Contact phone', 'rules' => ['nullable', 'string', 'max:30']],
                    'office_address' => ['type' => 'textarea', 'label' => 'Office address', 'rules' => ['nullable', 'string', 'max:500']],
                    'currency' => ['type' => 'text', 'label' => 'Currency', 'readOnly' => true, 'help' => 'ZAR in Phase 1. Other currencies arrive in Phase 2.', 'rules' => ['in:ZAR']],
                ],
            ],
            'auction' => [
                'class' => AuctionSettings::class,
                'label' => 'Auctions',
                'description' => 'Defaults for every auction. A listing can override its own duration.',
                'fields' => [
                    'default_duration_days' => ['type' => 'number', 'label' => 'Default auction length', 'suffix' => 'days', 'rules' => ['required', 'integer', 'min:1', 'max:120']],
                    'soft_close_enabled' => ['type' => 'boolean', 'label' => 'Soft close', 'help' => 'A bid near the end extends the auction, so nobody wins by bidding in the last second.', 'rules' => ['boolean']],
                    'soft_close_window_minutes' => ['type' => 'number', 'label' => 'Soft close window', 'suffix' => 'minutes before close', 'rules' => ['required', 'integer', 'min:1', 'max:60']],
                    'soft_close_extension_minutes' => ['type' => 'number', 'label' => 'Extend by', 'suffix' => 'minutes', 'rules' => ['required', 'integer', 'min:1', 'max:60']],
                    'closing_soon_hours' => ['type' => 'number', 'label' => '“Closing soon” badge', 'suffix' => 'hours before close', 'rules' => ['required', 'integer', 'min:1', 'max:336']],
                    'refund_delay_days' => ['type' => 'number', 'label' => 'Refund losing bidders after', 'suffix' => 'days', 'rules' => ['required', 'integer', 'min:0', 'max:60']],
                    'runner_up_hold_days' => ['type' => 'number', 'label' => 'Hold runner-up deposit for up to', 'suffix' => 'days', 'rules' => ['required', 'integer', 'min:1', 'max:90']],
                    'step_up_threshold' => ['type' => 'number', 'label' => 'One-time PIN for bids from', 'prefix' => 'R', 'rules' => ['required', 'integer', 'min:0']],
                    'default_suspension_months' => ['type' => 'number', 'label' => 'Suspension after a default', 'suffix' => 'months', 'rules' => ['required', 'integer', 'min:1', 'max:60']],
                    'hot_badge_min_bids' => ['type' => 'number', 'label' => '“Hot” badge from', 'suffix' => 'bids', 'rules' => ['required', 'integer', 'min:2', 'max:500']],
                    'new_badge_days' => ['type' => 'number', 'label' => '“New” badge for', 'suffix' => 'days after listing', 'rules' => ['required', 'integer', 'min:1', 'max:60']],
                ],
            ],
            'security' => [
                'class' => SecuritySettings::class,
                'label' => 'Security',
                'description' => 'Sign-in and back-office access rules.',
                'fields' => [
                    'staff_two_factor_required' => ['type' => 'boolean', 'label' => 'Staff two-factor authentication', 'help' => 'Must be on for go-live. Switch off only while testing.', 'rules' => ['boolean']],
                    'email_verification_required' => ['type' => 'boolean', 'label' => 'Buyers must verify their email address', 'help' => 'Must be on for go-live, once email sending works. While off, no verification emails are sent.', 'rules' => ['boolean']],
                    'session_lifetime_minutes' => ['type' => 'number', 'label' => 'Sign out after inactivity', 'suffix' => 'minutes', 'rules' => ['required', 'integer', 'min:15', 'max:1440']],
                    'admin_ip_allowlist' => ['type' => 'list', 'label' => 'Back-office IP allow-list', 'help' => 'One IP address or range (CIDR) per line. Leave empty to allow any address. Your current address must be included.', 'rules' => ['array', 'max:50'], 'itemRules' => ['string', 'max:64']],
                    'approvals_enabled' => ['type' => 'boolean', 'label' => 'Security approval codes', 'help' => 'Deletes, changes to public listings and finance approvals need a code emailed to the approvers below. Changing these security settings also needs a code.', 'rules' => ['boolean']],
                    'approver_emails' => ['type' => 'list', 'label' => 'Security approvers', 'help' => 'One email address per line. Every code goes to all of them; any one can give it to the person asking.', 'rules' => ['array', 'min:1', 'max:5'], 'itemRules' => ['email:rfc', 'max:160']],
                ],
            ],
        ];
    }

    /**
     * @return array{class: class-string<Settings>, label: string, description: string, fields: array<string, array<string, mixed>>}|null
     */
    public static function find(string $group): ?array
    {
        return self::groups()[$group] ?? null;
    }
}
