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

// Quotations: expiry and reminders every morning (sprint 3Q spec 5).
Schedule::command('quotes:daily')->dailyAt('07:00')->timezone('Africa/Johannesburg');

// Sprint 6b: closing-soon alerts (each once per bidder).
Schedule::command('auctions:closing-alerts')->everyMinute()->withoutOverlapping()->onOneServer();

// Sprint 6c: close ended auctions (soft close respected) and hand deposits to refunds.
Schedule::command('auctions:close')->everyMinute()->withoutOverlapping()->onOneServer();

// Sprint 7a: expire runner-up offers and remind buyers of settlement deadlines.
Schedule::command('settlement:daily')->dailyAt('07:30')->timezone('Africa/Johannesburg')->onOneServer();

// Sprint 8b: saved-search digests and 24-hour watchlist reminders.
Schedule::command('growth:alerts')->hourly()->withoutOverlapping()->onOneServer();

// Sprint 8c: gather suspicious-activity signals for review.
Schedule::command('compliance:scan')->hourly()->withoutOverlapping()->onOneServer();
