<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Automatic bidding (maximums) on by default. Switching it off stops new maximums only; ones already set keep working. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('auction.auto_bid_enabled', true);
    }
};
