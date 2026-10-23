<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Proof of funds is required for every Mining listing and for listings with a minimum at or above this (rand).
        $this->migrator->add('auction.proof_of_funds_threshold', 5000000);
    }
};
