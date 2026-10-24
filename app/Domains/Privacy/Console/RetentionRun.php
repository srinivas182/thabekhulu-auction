<?php

namespace App\Domains\Privacy\Console;

use App\Domains\Audit\Support\Audit;
use App\Domains\Privacy\Settings\PrivacySettings;
use App\Domains\Privacy\Support\RetentionSchedule;
use Illuminate\Console\Command;

/** Weekly: report what the retention schedule has due; purge only after the attorney's approval is recorded. */
class RetentionRun extends Command
{
    protected $signature = 'privacy:retention {--purge : Delete or anonymise what is due (only when approved)} {--scheduled : Weekly run: purge if approved, otherwise report}';

    protected $description = 'Report, or with approval apply, the data retention schedule';

    public function handle(): int
    {
        $due = RetentionSchedule::due();
        foreach (RetentionSchedule::categories() as $key => $c) {
            $this->line(str_pad($c['label'], 52).' '.str_pad((string) $due[$key], 6, ' ', STR_PAD_LEFT).'  ('.$c['kept'].')');
        }

        $purge = $this->option('purge') || ($this->option('scheduled') && app(PrivacySettings::class)->purge_enabled);
        if (! $purge) {
            Audit::record('privacy', 'retention_report', 'Retention report: '.array_sum($due).' records due', null, null, ['due' => $due]);
            $this->info('Report only. Nothing was deleted.');

            return self::SUCCESS;
        }

        $settings = app(PrivacySettings::class);
        if (! $settings->purge_enabled || $settings->approval_reference === '') {
            $this->error('Purging is not approved. A super admin must record the attorney’s approval of the schedule first.');

            return self::FAILURE;
        }

        $done = RetentionSchedule::purge();
        Audit::record('privacy', 'retention_purge', 'Retention purge: '.array_sum($done).' records removed or anonymised', null, null, ['done' => $done, 'approval' => $settings->approval_reference]);
        $this->info('Purged under approval '.$settings->approval_reference.': '.json_encode($done));

        return self::SUCCESS;
    }
}
