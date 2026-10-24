<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('auction.settlement_agreement_business_days', 5);
        $this->migrator->add('auction.settlement_balance_days', 30);
        $this->migrator->add('auction.runner_up_accept_days', 5);
    }
};
