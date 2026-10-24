<?php

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Event;

it('clears the settings cache whenever migrations finish', function () {
    expect(Event::hasListeners(MigrationsEnded::class))->toBeTrue()
        ->and(Event::hasListeners(NoPendingMigrations::class))->toBeTrue(); // "Nothing to migrate" must clear it too

    $this->artisan('migrate')->assertSuccessful(); // runs the listener; must not fail on an up-to-date database
});
