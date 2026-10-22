<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The auction shown in the home page hero; empty shows the text hero.
        $this->migrator->add('home.featured_listing', null);
    }
};
