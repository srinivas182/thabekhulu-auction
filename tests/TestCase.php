<?php

namespace Tests;

use App\Domains\Platform\Email\EmailSettings;
use App\Domains\Settings\Settings\SecuritySettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Production starts with email off; most tests describe a site whose email provider is set up.
        // Tests for the "off" state call withEmailProvider(false). Mail itself stays on the array mailer.
        $this->withEmailProvider();
        // Security approval codes are tested in their own tests (withApprovals()); elsewhere they'd only get in the way.
        $this->withApprovals(false);
    }

    protected function withApprovals(bool $on = true): void
    {
        if (! Schema::hasTable('settings') || ! DB::table('settings')->where('group', 'security')->where('name', 'approvals_enabled')->exists()) {
            return;
        }
        $s = app(SecuritySettings::class);
        $s->approvals_enabled = $on;
        $s->save();
    }

    protected function withEmailProvider(bool $on = true): void
    {
        if (! Schema::hasTable('settings') || ! DB::table('settings')->where('group', 'email')->exists()) {
            return;
        }
        $s = app(EmailSettings::class);
        $s->enabled = $on;
        $s->host = 'smtp.test';
        $s->port = 587;
        $s->username = 'test';
        $s->password = 'secret';
        $s->from_address = 'noreply@example.com';
        $s->save();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
