<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Bid confirmation codes: off | first (first bid in each auction) | every | threshold. Off while testing; security:check flags it. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('auction.step_up_mode', 'off');
    }
};
