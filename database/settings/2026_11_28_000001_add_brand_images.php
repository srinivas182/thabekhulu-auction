<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Site photos that don't belong to any listing: the home banner and the sign-in/sign-up pages. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('home.hero_image', null);
        $this->migrator->add('home.auth_image', null);
    }
};
