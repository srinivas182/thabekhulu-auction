<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the profile page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/profile'));
});

it('updates name, email and mobile, normalising the mobile number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Thandi',
            'last_name' => 'Mokoena',
            'email' => 'Thandi@Example.com',
            'mobile' => '082 555 1234',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->first_name)->toBe('Thandi')
        ->and($user->last_name)->toBe('Mokoena')
        ->and($user->name)->toBe('Thandi Mokoena')
        ->and($user->email)->toBe('thandi@example.com')
        ->and($user->mobile)->toBe('+27825551234')
        ->and($user->email_verified_at)->toBeNull();
});

it('keeps email verification when the email is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => 'Thandi',
        'last_name' => 'Mokoena',
        'email' => $user->email,
        'mobile' => '+27825551234',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('rejects a mobile number that is not South African', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => 'Thandi',
        'last_name' => 'Mokoena',
        'email' => $user->email,
        'mobile' => '+447700900123',
    ])->assertSessionHasErrors('mobile');
});

it('does not allow accounts to delete themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/profile')->assertMethodNotAllowed();

    expect(User::find($user->id))->not->toBeNull();
});
