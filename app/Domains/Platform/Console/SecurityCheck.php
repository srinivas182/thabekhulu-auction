<?php

namespace App\Domains\Platform\Console;

use App\Domains\Platform\Email\EmailConfig;
use App\Domains\Settings\Settings\SecuritySettings;
use Illuminate\Console\Command;

/**
 * Production readiness (Sprint 9a): run before go-live and after every
 * configuration change. Fails on settings that would weaken security.
 */
class SecurityCheck extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Check production security settings';

    /** The application key used only by the test suite (phpunit.xml); never valid in production. */
    public const TEST_KEY_PREFIX = 'base64:mqs3YhV';

    /** @return array<string, string|null> problem => null when fine */
    public static function problems(): array
    {
        $key = (string) config('app.key');
        $url = (string) config('app.url');

        return array_filter([
            'APP_ENV must be production' => config('app.env') !== 'production' ? 'is '.config('app.env') : null,
            'APP_DEBUG must be false' => config('app.debug') ? 'debug pages would leak secrets' : null,
            'APP_KEY must be a unique production key' => $key === '' || str_starts_with($key, self::TEST_KEY_PREFIX) ? 'missing or the test-suite key' : null,
            'APP_URL must use https' => ! str_starts_with($url, 'https://') ? $url : null,
            'Session cookies must be HTTPS-only' => ! config('session.secure') ? 'SESSION_SECURE_COOKIE is false' : null,
            'Session cookies must not be readable by scripts' => ! config('session.http_only') ? 'SESSION_HTTP_ONLY is false' : null,
            'Sessions must be encrypted' => ! config('session.encrypt') ? 'SESSION_ENCRYPT is false' : null,
            'Email sending must be enabled' => ! EmailConfig::sending() ? 'enable a provider in Back office » Email provider' : null,
            'Queues must run in the background' => config('queue.default') === 'sync' ? 'QUEUE_CONNECTION is sync' : null,
            'Live feed must use Reverb' => config('broadcasting.default') !== 'reverb' ? 'BROADCAST_CONNECTION is '.config('broadcasting.default') : null,
            'Email verification must be required' => ! app(SecuritySettings::class)->email_verification_required ? 'switched off in Settings » Security' : null,
            'Staff two-factor must be required' => ! app(SecuritySettings::class)->staff_two_factor_required ? 'switched off in Settings » Security' : null,
        ]);
    }

    public function handle(): int
    {
        $problems = self::problems();
        foreach ($problems as $check => $why) {
            $this->error("✗ {$check}: {$why}");
        }
        if ($problems === []) {
            $this->info('✓ All production security settings are in place.');

            return self::SUCCESS;
        }
        $this->error(count($problems).' setting(s) must be fixed before go-live.');

        return self::FAILURE;
    }
}
