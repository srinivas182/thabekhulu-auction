<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Letterhead and quotation defaults. Company details are placeholders until Thabekhulu supplies them. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('quotations.company_name', 'Thabekhulu Development Group');
        $this->migrator->add('quotations.registration_number', '');
        $this->migrator->add('quotations.vat_number', '');
        $this->migrator->add('quotations.address', '');
        $this->migrator->add('quotations.bank_details', '');
        $this->migrator->add('quotations.vat_rate_bps', 1500);
        $this->migrator->add('quotations.validity_days', 30);
        $this->migrator->add('quotations.approval_threshold', 500000);
        $this->migrator->add('quotations.reminder_days', 5);
        $this->migrator->add('quotations.number_prefix', 'TDG-Q');
    }
};
