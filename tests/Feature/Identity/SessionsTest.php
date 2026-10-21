<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => config(['session.driver' => 'database']));

function storeSession(User $user, string $id, string $agent): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $user->id, 'ip_address' => '196.25.1.1',
        'user_agent' => $agent, 'payload' => '', 'last_activity' => time() - 600,
    ]);
}

it('lists the account’s signed-in devices', function () {
    $user = User::factory()->create();
    storeSession($user, 'other-device', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36');

    $this->actingAs($user)->get(route('sessions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/sessions')
            ->where('sessions.0.device', 'Chrome on Windows')
            ->where('sessions.0.current', false));
});

it('signs out other devices after confirming the password', function () {
    $user = User::factory()->create();
    storeSession($user, 'other-device', 'Safari on iPhone');

    $this->actingAs($user)->delete(route('sessions.destroy'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');
    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeTrue();

    $this->actingAs($user)->delete(route('sessions.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();
    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
});
