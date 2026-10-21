<?php

use App\Domains\Identity\Enums\UserType;
use App\Models\User;
use Database\Seeders\AccessSeeder;

beforeEach(fn () => $this->seed(AccessSeeder::class));

function buyerDetails(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Sipho',
        'last_name' => 'Dlamini',
        'email' => 'Sipho@Example.com',
        'mobile' => '072 123 4567',
        'password' => 'Thabekhulu2026',
        'password_confirmation' => 'Thabekhulu2026',
        'terms' => '1',
    ], $overrides);
}

it('creates a buyer account with consent recorded', function () {
    $this->post(route('register.store'), buyerDetails())->assertSessionHasNoErrors();

    $user = User::where('email', 'sipho@example.com')->firstOrFail();

    expect($user->type)->toBe(UserType::Buyer)
        ->and($user->hasRole('buyer'))->toBeTrue()
        ->and($user->mobile)->toBe('+27721234567')
        ->and($user->terms_version)->toBe(config('platform.terms_version'))
        ->and($user->terms_accepted_at)->not->toBeNull()
        ->and($user->popia_consent_at)->not->toBeNull()
        ->and($user->ulid)->toHaveLength(26);
});

it('requires accepting the terms and privacy notice', function () {
    $this->post(route('register.store'), buyerDetails(['terms' => null]))
        ->assertSessionHasErrors('terms');

    $this->assertGuest();
});

it('requires a South African mobile number', function (string $mobile) {
    $this->post(route('register.store'), buyerDetails(['mobile' => $mobile]))
        ->assertSessionHasErrors('mobile');
})->with(['12345', '+447700900123', '0921234567']);

it('requires a 12-character password', function () {
    $this->post(route('register.store'), buyerDetails([
        'password' => 'Short123',
        'password_confirmation' => 'Short123',
    ]))->assertSessionHasErrors('password');
});

it('cannot register as staff through public sign-up', function () {
    $this->post(route('register.store'), buyerDetails(['type' => 'staff']));

    expect(User::firstOrFail()->type)->toBe(UserType::Buyer);
});

it('sends buyers to their account area, not the back office', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('dashboard'))->assertRedirect(route('account'));
    $this->actingAs($buyer)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($buyer)->get(route('account'))->assertOk();
});
