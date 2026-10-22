<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Audit retention (spec 8): prune entries older than AUDIT_RETENTION_DAYS (7 years).
Schedule::command('activitylog:clean --force')->dailyAt('02:30');

// Listings go live at their auction start time (sprint 3 spec 5.5).
Schedule::command('listings:go-live')->everyMinute()->withoutOverlapping();
