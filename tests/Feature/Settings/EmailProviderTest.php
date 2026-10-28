<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Platform\Console\SecurityCheck;
use App\Domains\Platform\Email\EmailConfig;
use App\Domains\Platform\Email\EmailSettings;
use App\Domains\Platform\Mail\TestEmail;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

it('sends nothing, and asks no one to verify, while email is off', function () {
    $this->withEmailProvider(false);
    EmailConfig::apply();

    expect(config('mail.default'))->toBe('array')
        ->and(EmailConfig::sending())->toBeFalse()
        ->and(User::factory()->create(['type' => 'buyer', 'email_verified_at' => null])->hasVerifiedEmail())->toBeTrue()
        ->and(SecurityCheck::problems())->toHaveKey('Email sending must be enabled');
});

it('lets an admin enable Brevo, keeps the key encrypted and write-only, and sends through it', function () {
    $this->withEmailProvider(false);
    DB::table('settings')->where('group', 'email')->where('name', 'password')->update(['payload' => json_encode(encrypt(''))]);
    app()->forgetInstance(EmailSettings::class);

    $form = ['enabled' => '1', 'provider' => 'brevo', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'username' => 'login@brevo.test',
        'from_address' => 'noreply@thabekhuludevelopment.org.za', 'from_name' => 'Thabekhulu Development Group'];
    $this->actingAs($this->admin)->put(route('admin.settings.email.update'), $form)->assertSessionHasErrors('password'); // a key is needed
    $this->actingAs($this->admin)->put(route('admin.settings.email.update'), $form + ['password' => 'xsmtpsib-secret-1234'])->assertSessionHasNoErrors();

    expect(DB::table('settings')->where('group', 'email')->where('name', 'password')->value('payload'))->not->toContain('xsmtpsib-secret-1234');
    $this->actingAs($this->admin)->get(route('admin.settings.email'))->assertInertia(fn ($p) => $p->where('settings.password_hint', '•••• 1234')->missing('settings.password')->where('sending', true));

    $this->actingAs($this->admin)->put(route('admin.settings.email.update'), $form)->assertSessionHasNoErrors(); // blank keeps the saved key
    app()->forgetInstance(EmailSettings::class);
    EmailConfig::apply();
    expect(config('mail.default'))->toBe('smtp')->and(config('mail.mailers.smtp.host'))->toBe('smtp-relay.brevo.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('xsmtpsib-secret-1234')->and(config('mail.mailers.smtp.scheme'))->toBe('smtp')
        ->and(config('mail.from.address'))->toBe('noreply@thabekhuludevelopment.org.za');

    Mail::fake();
    $this->actingAs($this->admin)->post(route('admin.settings.email.test'), ['to' => 'srini@example.com'])->assertSessionHasNoErrors();
    Mail::assertSent(TestEmail::class, fn ($m) => $m->hasTo('srini@example.com'));
});

it('rejects a full address in the server field and keeps the settings to settings.manage', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.email.update'), ['enabled' => '1', 'provider' => 'smtp', 'host' => 'https://mail.example.com', 'port' => 587,
        'username' => 'u', 'password' => 'p', 'from_address' => 'a@example.com', 'from_name' => 'T'])->assertSessionHasErrors('host');

    $support = User::factory()->staff(SystemRole::Support)->create();
    $this->actingAs($support)->get(route('admin.settings.email'))->assertForbidden();
});

it('never blocks artisan, including migrate, before the email settings exist', function () {
    DB::table('settings')->where('group', 'email')->delete();
    app()->forgetInstance(EmailSettings::class);

    EmailConfig::apply(); // must not throw
    expect(Artisan::call('list'))->toBe(0)->and(EmailConfig::sending())->toBeFalse();
});
