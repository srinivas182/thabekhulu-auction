<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Which escrow provider (if any) is offered for deposits next to trust-account EFT; "none" = EFT only.
        $this->migrator->add('payments.deposit_provider', 'none');
    }
};
