<?php

use App\Domains\Identity\Notifications\QueuedVerifyEmail;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

it('sends the verification email in the background', function () {
    Notification::fake();
    $this->seed(AccessSeeder::class);

    $this->post('/register', ['first_name' => 'Zola', 'last_name' => 'Ndaba', 'email' => 'zola@example.com', 'mobile' => '0825550123',
        'password' => 'Very-strong-pass-2026', 'password_confirmation' => 'Very-strong-pass-2026', 'terms' => '1', 'popia' => '1'])->assertSessionHasNoErrors();

    Notification::assertSentTo(User::where('email', 'zola@example.com')->first(), QueuedVerifyEmail::class);
    expect(new QueuedVerifyEmail)->toBeInstanceOf(ShouldQueue::class);
});

it('still registers when the mail server is unreachable', function () {
    // Real notifications and a real (unreachable) SMTP server: the job waits in the queue instead of failing the request.
    config(['queue.default' => 'database', 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.invalid', 'mail.mailers.smtp.port' => 465]);
    $this->seed(AccessSeeder::class);

    $this->post('/register', ['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'email' => 'thabo@example.com', 'mobile' => '0825550124',
        'password' => 'Very-strong-pass-2026', 'password_confirmation' => 'Very-strong-pass-2026', 'terms' => '1', 'popia' => '1'])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect(User::where('email', 'thabo@example.com')->exists())->toBeTrue()
        ->and(DB::table('jobs')->count())->toBeGreaterThan(0);
})->skip(fn () => ! Schema::hasTable('jobs'), 'needs the jobs table');
