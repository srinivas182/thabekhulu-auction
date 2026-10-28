<?php

use App\Domains\Identity\Notifications\QueuedVerifyEmail;
use App\Domains\Platform\Console\SecurityCheck;
use App\Domains\Settings\Settings\SecuritySettings;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);
});

function registerBuyer(string $email): User
{
    test()->post('/register', ['first_name' => 'Test', 'last_name' => 'Buyer', 'email' => $email, 'mobile' => '0825550123',
        'password' => 'Very-strong-pass-2026', 'password_confirmation' => 'Very-strong-pass-2026', 'terms' => '1', 'popia' => '1'])->assertSessionHasNoErrors();

    return User::where('email', $email)->firstOrFail();
}

it('requires buyers to verify their email by default', function () {
    $buyer = registerBuyer('on@example.com');

    Notification::assertSentTo($buyer, QueuedVerifyEmail::class);
    expect($buyer->hasVerifiedEmail())->toBeFalse();
    $this->actingAs($buyer)->get('/account')->assertRedirect(route('verification.notice'));
});

it('lets buyers straight in and sends nothing while the switch is off', function () {
    $s = app(SecuritySettings::class);
    $s->email_verification_required = false;
    $s->save();

    $buyer = registerBuyer('off@example.com');

    Notification::assertNotSentTo($buyer, QueuedVerifyEmail::class);
    expect($buyer->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($buyer)->get('/account')->assertOk();
    expect(SecurityCheck::problems())->toHaveKey('Email verification must be required');
});
