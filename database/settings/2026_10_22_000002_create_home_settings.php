<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('home.hero_title', 'Bid on South African property, from anywhere.');
        $this->migrator->add('home.hero_text', 'Homes, land, mining and commercial property sold by open online auction. Every bid is visible, every document is ready before you bid.');
        $this->migrator->add('home.steps', [
            ['title' => 'Register and verify', 'text' => 'Create your account and confirm your identity once.'],
            ['title' => 'Pay the listing deposit', 'text' => 'The deposit is shown on every listing and is refunded to your own account if you don’t win.'],
            ['title' => 'Bid until the close', 'text' => 'Raise your bid any time before the close. Highest bid at the close wins, subject to the sale terms.'],
        ]);
        $this->migrator->add('home.ffc_number', '');
        $this->migrator->add('home.show_calendar', true);
    }
};
