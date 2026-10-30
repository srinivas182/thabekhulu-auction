<?php

namespace App\Domains\Security;

use App\Domains\Audit\Support\Audit;
use App\Domains\Settings\Settings\SecuritySettings;
use Illuminate\Console\Command;

/** Emergency switch for approval codes (e.g. if email stops working). Needs server access; logged. */
class SecurityApprovalsCommand extends Command
{
    protected $signature = 'security:approvals {state : on or off}';

    protected $description = 'Switch security approval codes on or off (emergency use; recorded in the audit trail)';

    public function handle(): int
    {
        $arg = $this->argument('state');
        $state = is_string($arg) ? strtolower($arg) : '';
        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('Use: php artisan security:approvals on|off');

            return self::FAILURE;
        }
        $settings = app(SecuritySettings::class);
        $settings->approvals_enabled = $state === 'on';
        $settings->save();
        Audit::record('security', 'approvals_'.$state, 'Security approval codes switched '.$state.' from the server terminal');
        $this->info('Security approval codes are now '.$state.'.'.($state === 'off' ? ' Switch them back on as soon as possible.' : ''));

        return self::SUCCESS;
    }
}
