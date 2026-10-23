<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Trust account details are placeholders until Thabekhulu supplies them. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payments.trust_bank', '');
        $this->migrator->add('payments.trust_account_name', 'Thabekhulu Development Group Trust Account');
        $this->migrator->add('payments.trust_account_number', '');
        $this->migrator->add('payments.trust_branch_code', '');
        $this->migrator->add('payments.refund_business_days', 5);
        $this->migrator->add('payments.runner_up_hold_days', 14);
    }
};
