<?php

namespace Tests\Feature\Auth;

use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $this->seed(AccessSeeder::class);

        $response = $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'mobile' => '0821234567',
            'password' => 'Thabekhulu2026',
            'password_confirmation' => 'Thabekhulu2026',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
