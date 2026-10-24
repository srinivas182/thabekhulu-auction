<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // [up to (rand, null = no limit), increment (rand)] — placeholders until Thabekhulu confirms (spec 12).
        $this->migrator->add('auction.increment_bands', [[1000000, 10000], [5000000, 25000], [null, 50000]]);
    }
};
