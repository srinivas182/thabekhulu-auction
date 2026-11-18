<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Sellers share their documents with approved buyers only, so the legal sign-off
 * no longer holds deposits back by default. Staff can switch the gate on in Auction rules.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('auction.deposits_need_legal_signoff', false);
    }

    public function down(): void
    {
        $this->migrator->delete('auction.deposits_need_legal_signoff');
    }
};
