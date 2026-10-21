<?php

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Enums\UserType;
use App\Domains\Identity\Events\StaffInvitationAccepted;
use App\Domains\Identity\Events\StaffInvited;
use App\Domains\Identity\Models\StaffInvitation;
use App\Domains\Identity\Notifications\StaffInvitationNotification;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    Notification::fake();
    $this->admin = User::factory()->staff(SystemRole::SuperAdmin)->create();
});

function inviteAndCaptureToken(User $admin, string $email = 'new.staff@example.com', string $role = 'finance'): string
{
    test()->actingAs($admin)->post(route('admin.staff.invite'), ['email' => $email, 'role' => $role])
        ->assertSessionHasNoErrors();

    $token = null;
    Notification::assertSentTo(new AnonymousNotifiable, StaffInvitationNotification::class,
        function (StaffInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$token, $email) {
            $token = $notification->token;

            return $notifiable->routes['mail'] === $email;
        });

    auth()->logout();

    return (string) $token;
}

function acceptDetails(): array
{
    return [
        'first_name' => 'Lerato',
        'last_name' => 'Nkosi',
        'mobile' => '0831234567',
        'password' => 'Thabekhulu2026',
        'password_confirmation' => 'Thabekhulu2026',
    ];
}

it('emails an invitation and stores only a hash of the token', function () {
    Event::fake([StaffInvited::class]);

    $token = inviteAndCaptureToken($this->admin);

    $invitation = StaffInvitation::firstOrFail();
    expect($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->token_hash)->not->toBe($token)
        ->and($invitation->expires_at->diffInHours(now(), true))->toBeGreaterThan(71);

    Event::assertDispatched(StaffInvited::class);
});

it('creates a verified staff account with the invited role', function () {
    Event::fake([StaffInvitationAccepted::class]);
    $token = inviteAndCaptureToken($this->admin);

    $this->post(route('invitations.accept', $token), acceptDetails())
        ->assertRedirect(route('security.edit'));

    $staff = User::where('email', 'new.staff@example.com')->firstOrFail();

    expect($staff->type)->toBe(UserType::Staff)
        ->and($staff->hasRole('finance'))->toBeTrue()
        ->and($staff->email_verified_at)->not->toBeNull()
        ->and($staff->mobile)->toBe('+27831234567');

    $this->assertAuthenticatedAs($staff);
    Event::assertDispatched(StaffInvitationAccepted::class);
});

it('sends a new staff member to two-factor set-up before the back office', function () {
    $token = inviteAndCaptureToken($this->admin);
    $this->post(route('invitations.accept', $token), acceptDetails());

    $this->get(route('admin.dashboard'))->assertRedirect(route('security.edit'));
});

it('can only be used once', function () {
    $token = inviteAndCaptureToken($this->admin);
    $this->post(route('invitations.accept', $token), acceptDetails());
    auth()->logout();

    $this->post(route('invitations.accept', $token), acceptDetails())->assertSessionHasErrors('token');
    expect(User::where('email', 'new.staff@example.com')->count())->toBe(1);
});

it('expires after 72 hours', function () {
    $token = inviteAndCaptureToken($this->admin);

    $this->travel(73)->hours();

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn ($page) => $page->component('auth/accept-invitation')->where('valid', false));
    $this->post(route('invitations.accept', $token), acceptDetails())->assertSessionHasErrors('token');
});

it('cannot be used after it is revoked', function () {
    $token = inviteAndCaptureToken($this->admin);
    $invitation = StaffInvitation::firstOrFail();

    $this->actingAs($this->admin)->delete(route('admin.staff.invitations.revoke', $invitation));
    auth()->logout();

    $this->post(route('invitations.accept', $token), acceptDetails())->assertSessionHasErrors('token');
});

it('replaces an earlier pending invitation to the same email', function () {
    $first = inviteAndCaptureToken($this->admin);
    inviteAndCaptureToken($this->admin);

    expect(StaffInvitation::pending()->count())->toBe(1);
    $this->post(route('invitations.accept', $first), acceptDetails())->assertSessionHasErrors('token');
});

it('rejects an email that already has an account', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.invite'), ['email' => $this->admin->email, 'role' => 'finance'])
        ->assertSessionHasErrors('email');
});

it('cannot invite someone with a buyer or seller role', function (string $role) {
    $this->actingAs($this->admin)
        ->post(route('admin.staff.invite'), ['email' => 'x@example.com', 'role' => $role])
        ->assertSessionHasErrors('role');
})->with(['buyer', 'seller']);

it('returns 404 for an unknown token', function () {
    $this->post(route('invitations.accept', 'not-a-real-token'), acceptDetails())->assertNotFound();
});
