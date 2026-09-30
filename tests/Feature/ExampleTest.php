<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

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
        $response->assertSee('My Offices');
        $response->assertSee('Sign Out');
    }

    public function test_authorised_users_can_view_office_scoped_pages(): void
    {
        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_MANAGER,
            'offices' => ['MEL', 'SYD'],
        ]);

        $response = $this->actingAs($user)->get('/my-offices/clients?offices[]=MEL');

        $response->assertStatus(200);
        $response->assertSee('My Offices');
        $response->assertSee('Clients by Office');
        $response->assertSee('CL-1044');
        $response->assertSee('MEL');
        $response->assertDontSee('CL-1049');
        $response->assertDontSee('BNE - Brisbane');
    }

    public function test_users_without_my_offices_permissions_cannot_access_office_scoped_pages(): void
    {
        $user = User::factory()->create([
            'account_type' => 'Counsellor',
            'offices' => ['MEL'],
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertStatus(200)
            ->assertDontSee('My Offices');

        $this->actingAs($user)
            ->get('/my-offices/clients')
            ->assertForbidden();
    }

    public function test_authenticated_users_can_view_their_clients_with_filters_and_pagination(): void
    {
        $user = User::factory()->create(['name' => 'Jessica Morgan']);

        $this->actingAs($user)
            ->get('/clients?per_page=20')
            ->assertStatus(200)
            ->assertSee('My Clients')
            ->assertSee('clients-first-name')
            ->assertSee('clients-middle-name')
            ->assertSee('clients-surname')
            ->assertSee('clients-mobile')
            ->assertSee('clients-statuses')
            ->assertDontSee('Clients with past or active applications where you are the primary counsellor, secondary counsellor or migration agent.')
            ->assertSee('"client_id":1001', false)
            ->assertSee('"client_id":1015', false)
            ->assertDontSee('"client_id":1016', false)
            ->assertDontSee('"client_id":1017', false);

        $this->actingAs($user)
            ->get('/clients?surname=khan&statuses[]=Active')
            ->assertStatus(200)
            ->assertSee('id="clients-surname" value="khan"', false)
            ->assertSee('value="Active" data-status-option', false)
            ->assertSee('checked', false);
    }

    public function test_clients_csv_export_reflects_filters(): void
    {
        $user = User::factory()->create(['name' => 'Jessica Morgan']);

        $response = $this->actingAs($user)->get('/clients/export?statuses[]=Prospect&sort=surname&direction=desc');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Client ID","First Name","Middle Name",Surname,"Mobile Number",Status,Tag', $csv);
        $this->assertStringContainsString('1008,Mateo,Jose,Garcia', $csv);
        $this->assertStringContainsString('1002,Daniel,Min,Park', $csv);
        $this->assertStringNotContainsString('1001,Ana', $csv);
    }

    public function test_client_actions_have_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/clients/create')
            ->assertStatus(200)
            ->assertSee('Add Client');

        $this->actingAs($user)
            ->get('/clients/search')
            ->assertStatus(200)
            ->assertSee('Client Search')
            ->assertSee('Search By Client ID')
            ->assertSee('View My Clients')
            ->assertSee('Create Client');

        $this->actingAs($user)
            ->get('/clients/search?surname=Wilson')
            ->assertStatus(200)
            ->assertSee('CL-1016')
            ->assertSee('James')
            ->assertSee('Wilson')
            ->assertSee('1 client found');

        $this->actingAs($user)
            ->get('/clients/1001')
            ->assertStatus(200)
            ->assertSee('Client 1001');
    }
}
