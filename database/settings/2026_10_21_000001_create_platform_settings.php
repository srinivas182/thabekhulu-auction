<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Default values for Sprint 2 settings groups (spec section 6).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'Thabekhulu Development Group');
        $this->migrator->add('general.contact_email', 'info@thabekhuludevelopment.org.za');
        $this->migrator->add('general.contact_phone', '');
        $this->migrator->add('general.office_address', '');
        $this->migrator->add('general.currency', 'ZAR');

        $this->migrator->add('auction.default_duration_days', 30);
        $this->migrator->add('auction.soft_close_enabled', true);
        $this->migrator->add('auction.soft_close_window_minutes', 5);
        $this->migrator->add('auction.soft_close_extension_minutes', 5);
        $this->migrator->add('auction.closing_soon_hours', 72);
        $this->migrator->add('auction.refund_delay_days', 5);
        $this->migrator->add('auction.runner_up_hold_days', 14);
        $this->migrator->add('auction.step_up_threshold', 1000000);
        $this->migrator->add('auction.default_suspension_months', 12);
        $this->migrator->add('auction.hot_badge_min_bids', 10);
        $this->migrator->add('auction.new_badge_days', 7);

        $this->migrator->add('security.staff_two_factor_required', true);
        $this->migrator->add('security.session_lifetime_minutes', 120);
        $this->migrator->add('security.admin_ip_allowlist', []);
    }
};
