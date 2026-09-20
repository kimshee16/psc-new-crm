<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_login_screen_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('PSC CRM');
    }

    public function test_users_can_authenticate(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_users_can_view_the_home_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertSee('PSC CRM');
        $response->assertSee('Progress Study');
        $response->assertSee('Search client, application, lead or organisation');
        $response->assertSee('Welcome back, Jessica Morgan');
        $response->assertSee('Open Applications');
        $response->assertSee('Workflow Notifications');
        $response->assertSee('Sign Out');
    }
}
