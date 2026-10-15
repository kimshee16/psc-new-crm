<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EducationLevel;
use App\Models\Lead;
use App\Models\Location;
use App\Models\PscEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_manage_highest_education_level_configuration(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->get('/configuration?tab=education-levels')
            ->assertStatus(200)
            ->assertSee('Highest Education Level')
            ->assertSee('Junior High / Secondary (below Year 12)')
            ->assertSee('Senior High School (Year 12 / equivalent)');

        $this->actingAs($admin)
            ->from('/configuration')
            ->post('/configuration/education-levels', [
                'name' => 'Foundation Program',
                'sort_order' => '15',
                'active' => '1',
            ])
            ->assertRedirect('/configuration?tab=education-levels')
            ->assertSessionHas('success', 'Education level option was added.');

        $educationLevel = EducationLevel::query()->where('name', 'Foundation Program')->firstOrFail();

        $this->assertSame(15, (int) $educationLevel->sort_order);
        $this->assertTrue((bool) $educationLevel->active);

        $this->actingAs($admin)
            ->from('/configuration')
            ->patch('/configuration/education-levels/'.$educationLevel->id, [
                'name' => 'Foundation Studies',
                'sort_order' => '2',
                'active' => '0',
            ])
            ->assertRedirect('/configuration?tab=education-levels')
            ->assertSessionHas('success', 'Education level option was updated.');

        $this->assertDatabaseHas('education_levels', [
            'id' => $educationLevel->id,
            'name' => 'Foundation Studies',
            'sort_order' => 2,
            'active' => false,
        ]);

        $counsellor = User::factory()->create(['name' => 'Priya Raman']);

        $this->actingAs($admin)
            ->get('/intake/'.$counsellor->id)
            ->assertStatus(200)
            ->assertDontSee('Foundation Studies');

        $this->actingAs($admin)
            ->from('/configuration')
            ->delete('/configuration/education-levels/'.$educationLevel->id)
            ->assertRedirect('/configuration?tab=education-levels')
            ->assertSessionHas('success', 'Education level option was removed.');

        $this->assertDatabaseMissing('education_levels', [
            'id' => $educationLevel->id,
        ]);
    }

    public function test_admin_can_manage_location_configuration(): void
    {
        $admin = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->get('/configuration?tab=locations')
            ->assertStatus(200)
            ->assertSee('Locations')
            ->assertSee('Other Location')
            ->assertSee('Australia - Melbourne')
            ->assertSee('JKT - Jakarta');

        $this->actingAs($admin)
            ->from('/configuration?tab=locations')
            ->post('/configuration/locations', [
                'name' => 'New Zealand',
                'office_code' => 'SYD',
            ])
            ->assertRedirect('/configuration?tab=locations')
            ->assertSessionHas('success', 'Location option was added.');

        $location = Location::query()->where('name', 'New Zealand')->firstOrFail();

        $this->assertSame('SYD', $location->office_code);
        $this->assertTrue((bool) $location->active);

        $this->actingAs($admin)
            ->from('/configuration?tab=locations')
            ->patch('/configuration/locations/'.$location->id, [
                'name' => 'New Zealand - Auckland',
                'office_code' => 'MEL',
            ])
            ->assertRedirect('/configuration?tab=locations')
            ->assertSessionHas('success', 'Location option was updated.');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'New Zealand - Auckland',
            'office_code' => 'MEL',
            'active' => true,
        ]);

        $otherLocation = Location::query()->where('name', 'Other Location')->firstOrFail();

        $this->actingAs($admin)
            ->from('/configuration?tab=locations')
            ->delete('/configuration/locations/'.$otherLocation->id)
            ->assertRedirect('/configuration?tab=locations')
            ->assertSessionHas('error', 'Other Location cannot be removed.');

        $this->assertDatabaseHas('locations', [
            'id' => $otherLocation->id,
            'name' => 'Other Location',
        ]);

        $this->actingAs($admin)
            ->from('/configuration?tab=locations')
            ->delete('/configuration/locations/'.$location->id)
            ->assertRedirect('/configuration?tab=locations')
            ->assertSessionHas('success', 'Location option was removed.');

        $this->assertDatabaseMissing('locations', [
            'id' => $location->id,
        ]);
    }

    public function test_non_admin_users_cannot_manage_configuration(): void
    {
        $user = User::factory()->create([
            'account_type' => 'Counsellor',
        ]);

        $this->actingAs($user)
            ->get('/configuration')
            ->assertForbidden();
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

    public function test_leads_work_list_respects_permissions_and_admin_columns(): void
    {
        $admin = User::factory()->create([
            'name' => 'Office Admin',
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'offices' => ['MEL'],
        ]);
        $counsellor = User::factory()->create([
            'name' => 'Jessica Morgan',
            'account_type' => 'Counsellor',
            'offices' => ['MEL'],
        ]);
        $otherCounsellor = User::factory()->create([
            'name' => 'Priya Raman',
            'account_type' => 'Counsellor',
            'offices' => ['SYD'],
        ]);

        $visibleLead = $this->createLeadForList($counsellor, [
            'lead_id' => 2301,
            'client_id' => 2101,
            'first_name' => 'Kim',
            'middle_name' => 'Yves',
            'surname' => 'Ramirez',
            'status' => 'New',
            'priority' => 'high',
            'assigned_office_code' => 'MEL',
            'assigned_office' => 'Melbourne',
            'submitted_at' => now()->subDays(8),
        ]);
        $this->createLeadForList($otherCounsellor, [
            'lead_id' => 2302,
            'client_id' => 2102,
            'first_name' => 'Sydney',
            'surname' => 'Lead',
            'assigned_office_code' => 'SYD',
            'assigned_office' => 'Sydney',
        ]);

        $this->actingAs($admin)
            ->get('/leads?status=To%20be%20contacted&priority=high')
            ->assertStatus(200)
            ->assertSee('PSC-10')
            ->assertSee('Export List')
            ->assertSee('Clear Filter')
            ->assertSee('leads-client-name')
            ->assertDontSee('leads-client-id')
            ->assertDontSee('Client ID')
            ->assertSee('Admin Office')
            ->assertSee('Assigned to')
            ->assertSee('"lead_id":2301', false)
            ->assertSee('"client_full_name":"Kim Yves Ramirez"', false)
            ->assertSee('"status":"To be contacted"', false)
            ->assertSee('"priority":"high"', false)
            ->assertSee('"queue_age_level":"red"', false)
            ->assertDontSee('"lead_id":2302', false);

        $this->actingAs($counsellor)
            ->get('/leads')
            ->assertStatus(200)
            ->assertSee('"lead_id":'.$visibleLead->lead_id, false)
            ->assertSee('Export List')
            ->assertDontSee('Admin Office')
            ->assertDontSee('Assigned to')
            ->assertDontSee('"assigned_name"', false)
            ->assertDontSee('"lead_id":2302', false);
    }

    public function test_leads_csv_export_reflects_filters_and_sort(): void
    {
        $admin = User::factory()->create([
            'name' => 'Office Admin',
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'offices' => [],
        ]);
        $melCounsellor = User::factory()->create(['name' => 'Jessica Morgan', 'offices' => ['MEL']]);
        $sydCounsellor = User::factory()->create(['name' => 'Priya Raman', 'offices' => ['SYD']]);

        $this->createLeadForList($melCounsellor, [
            'lead_id' => 2401,
            'client_id' => 2201,
            'first_name' => 'Ana',
            'surname' => 'Santos',
            'status' => 'Contacted',
            'priority' => 'medium',
            'assigned_office_code' => 'MEL',
            'assigned_office' => 'Melbourne',
        ]);
        $this->createLeadForList($sydCounsellor, [
            'lead_id' => 2402,
            'client_id' => 2202,
            'first_name' => 'Daniel',
            'surname' => 'Park',
            'status' => 'New',
            'priority' => 'high',
            'assigned_office_code' => 'SYD',
            'assigned_office' => 'Sydney',
            'submitted_at' => now()->subDays(4),
        ]);
        $this->createLeadForList($sydCounsellor, [
            'lead_id' => 2403,
            'client_id' => 2203,
            'first_name' => 'Kim',
            'surname' => 'Ramirez',
            'status' => 'New',
            'priority' => 'high',
            'assigned_office_code' => 'SYD',
            'assigned_office' => 'Sydney',
            'submitted_at' => now()->subDays(8),
        ]);

        $response = $this->actingAs($admin)->get('/leads/export?status=To%20be%20contacted&priority=high&office=SYD&sort=client_full_name&direction=desc');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Client,Status,Priority,"Assigned To","Admin Office","Date Last Modified","Days in Queue"', $csv);
        $this->assertStringContainsString('"Kim Ramirez","To be contacted",high,"Priya Raman",Sydney', $csv);
        $this->assertStringContainsString('"Daniel Park","To be contacted",high,"Priya Raman",Sydney', $csv);
        $this->assertStringNotContainsString('2201,Ana', $csv);
        $this->assertLessThan(strpos($csv, 'Daniel Park'), strpos($csv, 'Kim Ramirez'));
    }

    public function test_lead_record_can_be_viewed_and_saved(): void
    {
        $counsellor = User::factory()->create([
            'name' => 'Jessica Morgan',
            'account_type' => 'Counsellor',
            'offices' => ['MEL'],
        ]);
        $secondary = User::factory()->create([
            'name' => 'Priya Raman',
            'account_type' => 'Counsellor',
            'offices' => ['SYD'],
        ]);
        $agent = User::factory()->create([
            'name' => 'Nina Patel',
            'account_type' => 'Counsellor',
            'offices' => ['MEL'],
        ]);
        $event = PscEvent::create([
            'name' => 'EduExpo Ball 2026',
            'starts_at' => '2026-11-18 10:00:00',
            'location' => 'Melbourne',
            'active' => true,
        ]);
        $lead = $this->createLeadForList($counsellor, [
            'lead_id' => 2501,
            'client_id' => 2301,
            'first_name' => 'Rika',
            'surname' => 'Supardi',
            'status' => 'New',
            'priority' => 'medium',
            'assigned_office_code' => 'MEL',
            'assigned_office' => 'Melbourne',
        ]);

        $this->actingAs($counsellor)
            ->get('/leads/'.$lead->lead_id)
            ->assertStatus(200)
            ->assertSee('Lead ID 2501 - Rika Supardi')
            ->assertSee('Rika Supardi')
            ->assertSee('Google Drive')
            ->assertSee('Create Application')
            ->assertSee('Back to leads')
            ->assertSee('View client')
            ->assertSee('name="prospective_service"', false)
            ->assertSee('name="critical_date"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('tel:+61412555000', false)
            ->assertSee('mailto:rika.supardi.2301@example.test', false);

        $this->actingAs($counsellor)
            ->patch('/leads/'.$lead->lead_id, [
                'prospective_service' => 'Study',
                'status' => 'Consulting',
                'priority' => 'high',
                'critical_date' => '2026-11-25',
                'source' => 'Event',
                'event_name' => $event->name,
                'highest_education_level' => 'Advanced Diploma / Associate Degree',
                'assigned_name' => $counsellor->name,
                'secondary_counsellor' => $secondary->name,
                'migration_agent' => $agent->name,
                'notes' => 'Wants to study engineering',
            ])
            ->assertRedirect('/leads/'.$lead->lead_id)
            ->assertSessionHas('success', 'Lead changes were saved.');

        $this->assertDatabaseHas('leads', [
            'lead_id' => 2501,
            'prospective_service' => 'Study',
            'status' => 'Consulting',
            'priority' => 'high',
            'critical_date' => '2026-11-25',
            'source' => 'Event',
            'event_name' => 'EduExpo Ball 2026',
            'highest_education_level' => 'Advanced Diploma / Associate Degree',
            'assigned_name' => 'Jessica Morgan',
            'secondary_counsellor' => 'Priya Raman',
            'migration_agent' => 'Nina Patel',
            'notes' => 'Wants to study engineering',
        ]);
    }

    public function test_lead_record_uses_lead_visibility_rules(): void
    {
        $owner = User::factory()->create([
            'name' => 'Jessica Morgan',
            'account_type' => 'Counsellor',
            'offices' => ['MEL'],
        ]);
        $other = User::factory()->create([
            'name' => 'Priya Raman',
            'account_type' => 'Counsellor',
            'offices' => ['SYD'],
        ]);
        $lead = $this->createLeadForList($owner, [
            'lead_id' => 2502,
            'client_id' => 2302,
            'first_name' => 'Private',
            'surname' => 'Lead',
            'assigned_office_code' => 'MEL',
            'assigned_office' => 'Melbourne',
        ]);

        $this->actingAs($other)
            ->get('/leads/'.$lead->lead_id)
            ->assertNotFound();

        $this->actingAs($other)
            ->patch('/leads/'.$lead->lead_id, [
                'prospective_service' => 'Study',
                'status' => 'Contacted',
                'priority' => 'low',
                'critical_date' => '',
                'source' => 'Manual',
                'event_name' => '',
                'highest_education_level' => '',
                'assigned_name' => $other->name,
                'secondary_counsellor' => '',
                'migration_agent' => '',
                'notes' => 'Should not save',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('leads', [
            'lead_id' => 2502,
            'notes' => 'Should not save',
        ]);
    }

    public function test_client_actions_have_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/clients/create')
            ->assertStatus(200)
            ->assertSee('New Client')
            ->assertSee('Client ID')
            ->assertSee('Admin Office')
            ->assertSee('Active');

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

    public function test_users_can_manually_create_a_client(): void
    {
        $user = User::factory()->create([
            'name' => 'Jessica Morgan',
            'offices' => ['SYD'],
        ]);

        $this->actingAs($user)
            ->get('/clients/create')
            ->assertStatus(200)
            ->assertSee('value="Sydney" selected', false)
            ->assertSee('value="Active" selected', false);

        $response = $this->actingAs($user)->post('/clients', [
            'first_name' => 'Kim',
            'middle_name' => 'Yves',
            'surname' => 'Ramirez',
            'dob' => '1997-06-10',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 111',
            'email' => 'kim@example.test',
            'nationality' => 'Philippine',
            'current_location' => 'Sydney',
            'street' => '10 George Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'admin_office' => 'Sydney',
            'client_status' => 'Active',
            'notes' => 'Initial manual client note',
        ]);

        $response
            ->assertRedirect('/clients/1018')
            ->assertSessionHas('success', 'Success! Client ID 1018 created for Kim Yves Ramirez');

        $this->assertDatabaseHas('clients', [
            'client_id' => 1018,
            'created_by_user_id' => $user->id,
            'first_name' => 'Kim',
            'middle_name' => 'Yves',
            'surname' => 'Ramirez',
            'dob' => '1997-06-10',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 111',
            'mobile' => '+61 412 555 111',
            'email' => 'kim@example.test',
            'client_status' => 'Active',
            'admin_office' => 'Sydney',
        ]);

        $createdNotes = json_decode((string) Client::where('client_id', 1018)->value('notes'), true);

        $this->assertSame('Initial manual client note', $createdNotes[0]['body'] ?? null);
        $this->assertSame('Jessica Morgan', $createdNotes[0]['author'] ?? null);
        $this->assertNotEmpty($createdNotes[0]['datetime'] ?? null);

        $this->actingAs($user)
            ->get('/clients/1018')
            ->assertStatus(200)
            ->assertSee('Kim Yves Ramirez')
            ->assertSee('kim@example.test')
            ->assertSee('Initial manual client note')
            ->assertSee('Success! Client ID 1018 created for Kim Yves Ramirez');

        $this->actingAs($user)
            ->get('/clients?per_page=20')
            ->assertStatus(200)
            ->assertSee('"client_id":1018', false)
            ->assertSee('"first_name":"Kim"', false);
    }

    public function test_manual_client_creation_requires_mandatory_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/clients/create')
            ->post('/clients', [
                'first_name' => '',
                'phone_country_code' => '',
                'phone_number' => '',
                'email' => '',
                'nationality' => '',
                'current_location' => '',
                'admin_office' => '',
                'client_status' => '',
            ])
            ->assertRedirect('/clients/create')
            ->assertSessionHasErrors([
                'first_name',
                'phone_country_code',
                'phone_number',
                'email',
                'nationality',
                'current_location',
                'admin_office',
                'client_status',
            ]);

        $this->actingAs($user)
            ->get('/clients/create')
            ->assertStatus(200)
            ->assertSee('The client was not created. Complete the missing mandatory data.');
    }

    public function test_manual_client_creation_warns_about_duplicate_full_name_and_dob(): void
    {
        $user = User::factory()->create(['name' => 'Jessica Morgan']);

        $payload = [
            'first_name' => 'Ana',
            'middle_name' => 'Maria',
            'surname' => 'Santos',
            'dob' => '2000-04-12',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 222',
            'email' => 'ana.duplicate@example.test',
            'nationality' => 'Philippine',
            'current_location' => 'Melbourne',
            'admin_office' => 'Melbourne',
            'client_status' => 'Active',
        ];

        $this->actingAs($user)
            ->from('/clients/create')
            ->post('/clients', $payload)
            ->assertRedirect('/clients/create')
            ->assertSessionHas('duplicateClient');

        $this->actingAs($user)
            ->get('/clients/create')
            ->assertStatus(200)
            ->assertSee('Possible duplicate client found.')
            ->assertSee('Ana Maria Santos already exists as CL-1001');

        $this->actingAs($user)
            ->post('/clients', $payload + ['proceed_duplicate' => '1'])
            ->assertRedirect('/clients/1018')
            ->assertSessionHas('success', 'Success! Client ID 1018 created for Ana Maria Santos');
    }

    public function test_client_record_can_be_edited_with_validation_and_success_feedback(): void
    {
        $user = User::factory()->create(['name' => 'Jessica Morgan']);

        $this->actingAs($user)
            ->get('/clients/1001')
            ->assertStatus(200)
            ->assertSee('Edit')
            ->assertDontSee('Save changes');

        $this->actingAs($user)
            ->post('/clients/1001/edit')
            ->assertRedirect('/clients/1001?editing=1');

        $this->actingAs($user)
            ->get('/clients/1001?editing=1')
            ->assertStatus(200)
            ->assertSee('Save changes')
            ->assertSee('Discard');

        $this->actingAs($user)
            ->from('/clients/1001?editing=1')
            ->patch('/clients/1001', [
                'first_name' => '',
                'dob' => now()->addDay()->toDateString(),
                'phone_country_code' => '',
                'phone_number' => '0412.ABC',
                'email' => 'invalid-email',
                'nationality' => 'Atlantis',
                'current_location' => '',
                'postcode' => '12345',
                'client_status' => '',
            ])
            ->assertRedirect('/clients/1001?editing=1')
            ->assertSessionHasErrors([
                'first_name',
                'dob',
                'phone_country_code',
                'phone_number',
                'email',
                'nationality',
                'current_location',
                'postcode',
                'client_status',
            ]);

        $this->actingAs($user)
            ->patch('/clients/1001', [
                'first_name' => 'Ana',
                'middle_name' => 'Maria',
                'surname' => '',
                'dob' => '1998-02-03',
                'phone_country_code' => '+61',
                'phone_number' => '412 555 999',
                'email' => 'ana.updated@example.test',
                'nationality' => 'Philippine',
                'current_location' => 'Sydney',
                'street' => '5 King Street',
                'suburb' => 'Sydney',
                'state' => 'NSW',
                'postcode' => '2000',
                'admin_office' => 'Sydney',
                'client_status' => 'Active',
                'current_visa' => 'Student visa',
                'visa_expiry' => '2027-03-04',
                'notes' => 'Updated record note',
            ])
            ->assertRedirect('/clients/1001')
            ->assertSessionHas('success', 'Success! Client changes were saved.');

        $this->assertDatabaseHas('clients', [
            'client_id' => 1001,
            'first_name' => 'Ana',
            'surname' => '',
            'dob' => '1998-02-03',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 999',
            'mobile' => '+61 412 555 999',
            'email' => 'ana.updated@example.test',
            'current_location' => 'Sydney',
            'postcode' => '2000',
        ]);

        $this->actingAs($user)
            ->get('/clients/1001')
            ->assertStatus(200)
            ->assertSee('Success! Client changes were saved.')
            ->assertSee('ana.updated@example.test')
            ->assertSee('Updated record note')
            ->assertSee('Edit')
            ->assertDontSee('Save changes');
    }

    public function test_existing_client_can_create_manual_lead_from_applications_section(): void
    {
        $user = User::factory()->create([
            'name' => 'Jessica Morgan',
            'offices' => ['MEL'],
        ]);
        $client = Client::create([
            'client_id' => 2601,
            'created_by_user_id' => $user->id,
            'first_name' => 'Existing',
            'middle_name' => '',
            'surname' => 'Client',
            'dob' => '1998-03-12',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 888',
            'mobile' => '+61 412 555 888',
            'email' => 'existing.client@example.test',
            'nationality' => 'Philippine',
            'current_location' => 'Melbourne',
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'admin_office' => 'Melbourne',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'notes' => '',
            'primary_counsellor' => 'Jessica Morgan',
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Manual',
        ]);

        $this->actingAs($user)
            ->get('/clients/2601')
            ->assertStatus(200)
            ->assertSee('Active Applications and Leads')
            ->assertSee('Create Application')
            ->assertSee('name="create_lead"', false)
            ->assertDontSee('name="create_lead" value="1" type="checkbox" required checked', false);

        $this->actingAs($user)
            ->from('/clients/2601')
            ->post('/clients/2601/applications', [
                'prospective_service' => 'Study',
                'create_lead' => '1',
            ])
            ->assertRedirect('/clients/2601')
            ->assertSessionHas('success', 'Success! Lead ID 2201 created for Existing Client.');

        $this->assertDatabaseHas('leads', [
            'lead_id' => 2201,
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'assigned_name' => 'Jessica Morgan',
            'assigned_office_code' => 'MEL',
            'assigned_office' => 'Melbourne',
            'form_type' => 'manual_client_record',
            'source' => 'Manual',
            'status' => 'New',
            'priority' => 'medium',
            'prospective_service' => 'Study',
            'email' => 'existing.client@example.test',
        ]);

        $this->actingAs($user)
            ->get('/clients/2601')
            ->assertStatus(200)
            ->assertSee('Lead ID 2201')
            ->assertSee('Study Lead')
            ->assertSee('To be contacted');
    }

    public function test_client_notes_are_saved_as_individual_sticky_notes(): void
    {
        $user = User::factory()->create(['name' => 'Jessica Morgan']);
        $payload = [
            'first_name' => 'Ana',
            'middle_name' => 'Maria',
            'surname' => 'Santos',
            'dob' => '1998-02-03',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 999',
            'email' => 'ana.notes@example.test',
            'nationality' => 'Philippine',
            'current_location' => 'Sydney',
            'street' => '5 King Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'admin_office' => 'Sydney',
            'client_status' => 'Active',
            'current_visa' => 'Student visa',
            'visa_expiry' => '2027-03-04',
        ];

        $this->actingAs($user)->post('/clients/1001/edit');

        $this->actingAs($user)
            ->patch('/clients/1001', $payload + [
                'note_bodies' => ['First sticky note', '', 'Second sticky note'],
            ])
            ->assertRedirect('/clients/1001');

        $savedNotes = json_decode((string) Client::where('client_id', 1001)->value('notes'), true);

        $this->assertCount(2, $savedNotes);
        $this->assertSame('First sticky note', $savedNotes[0]['body'] ?? null);
        $this->assertSame('Second sticky note', $savedNotes[1]['body'] ?? null);
        $this->assertSame('Jessica Morgan', $savedNotes[0]['author'] ?? null);
        $this->assertNotEmpty($savedNotes[0]['datetime'] ?? null);

        $this->actingAs($user)
            ->get('/clients/1001')
            ->assertStatus(200)
            ->assertSee('client-note', false)
            ->assertSee('First sticky note')
            ->assertSee('Second sticky note')
            ->assertSee('Added by Jessica Morgan');

        $this->actingAs($user)->post('/clients/1001/edit');

        $this->actingAs($user)
            ->patch('/clients/1001', $payload + [
                'note_bodies' => ['Second sticky note'],
            ])
            ->assertRedirect('/clients/1001');

        $remainingNotes = json_decode((string) Client::where('client_id', 1001)->value('notes'), true);

        $this->assertCount(1, $remainingNotes);
        $this->assertSame('Second sticky note', $remainingNotes[0]['body'] ?? null);
        $this->assertSame('Jessica Morgan', $remainingNotes[0]['author'] ?? null);
        $this->assertNotEmpty($remainingNotes[0]['datetime'] ?? null);
    }

    public function test_client_record_edit_lock_blocks_other_users_until_released(): void
    {
        $userA = User::factory()->create(['name' => 'Jessica Morgan']);
        $userB = User::factory()->create(['name' => 'Priya Raman']);

        $this->actingAs($userA)
            ->post('/clients/1002/edit')
            ->assertRedirect('/clients/1002?editing=1');

        $this->actingAs($userB)
            ->post('/clients/1002/edit')
            ->assertRedirect('/clients/1002')
            ->assertSessionHas('warning', 'This client record is currently being edited by Jessica Morgan.');

        $this->actingAs($userB)
            ->get('/clients/1002')
            ->assertStatus(200)
            ->assertSee('This client record is currently being edited.')
            ->assertSee('disabled', false);

        $this->actingAs($userA)
            ->post('/clients/1002/discard')
            ->assertRedirect('/clients/1002');

        $this->actingAs($userB)
            ->post('/clients/1002/edit')
            ->assertRedirect('/clients/1002?editing=1');
    }

    public function test_client_passport_photo_can_be_uploaded_replaced_and_deleted_on_save(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Jessica Morgan']);
        $payload = [
            'first_name' => 'Ana',
            'middle_name' => 'Maria',
            'surname' => 'Santos',
            'dob' => '1998-02-03',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 999',
            'email' => 'ana.updated@example.test',
            'nationality' => 'Philippine',
            'current_location' => 'Sydney',
            'street' => '5 King Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'admin_office' => 'Sydney',
            'client_status' => 'Active',
            'current_visa' => 'Student visa',
            'visa_expiry' => '2027-03-04',
            'notes' => 'Updated record note',
        ];

        $this->actingAs($user)->post('/clients/1001/edit');

        $this->actingAs($user)
            ->patch('/clients/1001', $payload + [
                'passport_photo' => UploadedFile::fake()->image('passport-photo.jpg', 480, 620)->size(256),
            ])
            ->assertRedirect('/clients/1001')
            ->assertSessionHas('success', 'Success! Client changes were saved.');

        $firstPhotoPath = (string) Client::where('client_id', 1001)->value('passport_photo_path');

        $this->assertStringStartsWith('client-passport-photos/', $firstPhotoPath);
        Storage::disk('public')->assertExists($firstPhotoPath);

        $this->actingAs($user)
            ->get('/clients/1001')
            ->assertStatus(200)
            ->assertSee('/storage/'.$firstPhotoPath, false);

        $this->actingAs($user)->post('/clients/1001/edit');

        $this->actingAs($user)
            ->patch('/clients/1001', $payload + [
                'passport_photo' => UploadedFile::fake()->image('replacement-photo.png', 480, 620)->size(300),
            ])
            ->assertRedirect('/clients/1001');

        $replacementPhotoPath = (string) Client::where('client_id', 1001)->value('passport_photo_path');

        $this->assertNotSame($firstPhotoPath, $replacementPhotoPath);
        Storage::disk('public')->assertMissing($firstPhotoPath);
        Storage::disk('public')->assertExists($replacementPhotoPath);

        $this->actingAs($user)->post('/clients/1001/edit');

        $this->actingAs($user)
            ->patch('/clients/1001', $payload + [
                'delete_passport_photo' => '1',
            ])
            ->assertRedirect('/clients/1001');

        $this->assertNull(Client::where('client_id', 1001)->value('passport_photo_path'));
        Storage::disk('public')->assertMissing($replacementPhotoPath);
    }

    public function test_website_contact_form_creates_client_and_lead_then_redirects(): void
    {
        $user = User::factory()->create([
            'name' => 'Jessica Morgan',
            'offices' => ['MEL'],
        ]);

        $this->get('/contact')
            ->assertStatus(200)
            ->assertSee('Contact Us')
            ->assertSee('name="first_name"', false)
            ->assertSee('name="captcha"', false)
            ->assertSee('id="captcha-image"', false)
            ->assertSee('/website/captcha/image', false)
            ->assertSee('Australia - Melbourne');

        $response = $this
            ->withSession(['psc.website_forms.captcha' => 'ABC123'])
            ->from('/contact')
            ->post('/contact', $this->websiteLeadPayload([
                'captcha' => 'ABC123',
                'enquiry' => 'I want to study nursing.',
            ]));

        $response->assertRedirect('https://progress-study.com/contact-received');

        $client = Client::query()->where('email', 'kim.website@example.test')->firstOrFail();

        $this->assertSame(1018, (int) $client->client_id);
        $this->assertSame('Prospect', $client->client_status);
        $this->assertSame('Web', $client->tag);
        $this->assertSame('Jessica Morgan', $client->primary_counsellor);
        $this->assertSame('+61', $client->phone_country_code);
        $this->assertSame('412 555 333', $client->phone_number);

        $websiteNotes = json_decode((string) $client->notes, true);

        $this->assertSame('Website enquiry: I want to study nursing.', $websiteNotes[0]['body'] ?? null);
        $this->assertSame('PSC Website Form', $websiteNotes[0]['author'] ?? null);
        $this->assertNotEmpty($websiteNotes[0]['datetime'] ?? null);

        $this->assertDatabaseHas('leads', [
            'lead_id' => 2201,
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'assigned_name' => 'Jessica Morgan',
            'assigned_office_code' => 'MEL',
            'form_type' => 'contact_us',
            'source' => 'Contact us',
            'status' => 'New',
            'email' => 'kim.website@example.test',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 333',
            'mobile' => '+61 412 555 333',
            'current_location' => 'Australia - Melbourne',
        ]);
    }

    public function test_website_consultation_form_links_existing_client_and_redirects(): void
    {
        $user = User::factory()->create([
            'name' => 'Priya Raman',
            'offices' => ['SYD'],
        ]);
        $client = Client::create([
            'client_id' => 1018,
            'created_by_user_id' => $user->id,
            'first_name' => 'Existing',
            'middle_name' => '',
            'surname' => 'Client',
            'dob' => '1998-03-12',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 444',
            'mobile' => '+61 412 555 444',
            'email' => 'existing.website@example.test',
            'nationality' => 'Australia',
            'current_location' => 'Sydney',
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'admin_office' => 'Sydney',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'notes' => '',
            'primary_counsellor' => 'Priya Raman',
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Web',
        ]);

        $this
            ->withSession(['psc.website_forms.captcha' => 'XYZ789'])
            ->from('/book-a-free-consultation')
            ->post('/book-a-free-consultation', $this->websiteLeadPayload([
                'first_name' => 'Existing',
                'surname' => 'Client',
                'email' => 'updated.website@example.test',
                'phone_number' => '499 555 444',
                'dob' => '1998-03-12',
                'current_location' => 'Australia - Sydney',
                'captcha' => 'XYZ789',
            ]))
            ->assertRedirect('https://progress-study.com/request-received');

        $this->assertDatabaseCount('clients', 1);
        $client->refresh();

        $this->assertSame('updated.website@example.test', $client->email);
        $this->assertSame('499 555 444', $client->phone_number);
        $this->assertDatabaseHas('leads', [
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'assigned_office_code' => 'SYD',
            'form_type' => 'book_consultation',
            'source' => 'Book a free consultation',
        ]);
    }

    public function test_website_forms_require_a_valid_captcha(): void
    {
        $refresh = $this
            ->withSession(['psc.website_forms.captcha' => 'ABC123'])
            ->getJson('/website/captcha/refresh');

        $refresh
            ->assertOk()
            ->assertJsonStructure(['image_url']);

        $this->assertArrayNotHasKey('captcha', $refresh->json());

        $this
            ->withSession(['psc.website_forms.captcha' => 'ABC123'])
            ->get('/website/captcha/image')
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');

        $this
            ->withSession(['psc.website_forms.captcha' => 'ABC123'])
            ->from('/contact')
            ->post('/contact', $this->websiteLeadPayload([
                'captcha' => 'WRONG1',
            ]))
            ->assertRedirect('/contact')
            ->assertSessionHasErrors(['captcha']);

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_counsellor_intake_form_creates_client_and_lead_without_captcha(): void
    {
        $counsellor = User::factory()->create([
            'name' => 'Priya Raman',
            'offices' => ['SYD'],
        ]);

        $this->get('/intake/'.$counsellor->id)
            ->assertStatus(200)
            ->assertSee('Welcome to Progress Study Consultancy')
            ->assertSee('Counsellor: Priya')
            ->assertSee('name="dob"', false)
            ->assertSee('name="highest_education_level"', false)
            ->assertDontSee('name="captcha"', false);

        $this
            ->from('/intake/'.$counsellor->id)
            ->post('/intake/'.$counsellor->id, $this->counsellorIntakePayload([
                'dob' => '1999-05-20',
                'highest_education_level' => 'Bachelor Degree',
                'enquiry' => 'I want to study aged care.',
            ]))
            ->assertRedirect('/intake/'.$counsellor->id.'/thanks');

        $client = Client::query()->where('email', 'kim.intake@example.test')->firstOrFail();

        $this->assertSame(1018, (int) $client->client_id);
        $this->assertSame($counsellor->id, $client->created_by_user_id);
        $this->assertSame('Priya Raman', $client->primary_counsellor);
        $this->assertSame('Sydney', $client->admin_office);
        $this->assertSame('Prospect', $client->client_status);
        $this->assertSame('Walk-in', $client->tag);
        $this->assertSame('1999-05-20', $client->dob?->toDateString());

        $notes = json_decode((string) $client->notes, true);

        $this->assertStringContainsString('Highest education level: Bachelor Degree', $notes[0]['body'] ?? '');
        $this->assertStringContainsString('Enquiry: I want to study aged care.', $notes[0]['body'] ?? '');
        $this->assertSame('Priya Raman intake form', $notes[0]['author'] ?? null);

        $this->assertDatabaseHas('leads', [
            'lead_id' => 2201,
            'client_id' => $client->id,
            'assigned_user_id' => $counsellor->id,
            'assigned_name' => 'Priya Raman',
            'assigned_office_code' => 'SYD',
            'assigned_office' => 'Sydney',
            'form_type' => 'counsellor_intake',
            'source' => 'PSC-7 counsellor intake',
            'email' => 'kim.intake@example.test',
            'dob' => '1999-05-20',
            'highest_education_level' => 'Bachelor Degree',
        ]);
    }

    public function test_counsellor_intake_form_links_existing_client_and_reassigns_counsellor_office(): void
    {
        $oldUser = User::factory()->create(['name' => 'Old Counsellor', 'offices' => ['MEL']]);
        $counsellor = User::factory()->create([
            'name' => 'Jessica Morgan',
            'offices' => ['BNE'],
        ]);
        $client = Client::create([
            'client_id' => 1018,
            'created_by_user_id' => $oldUser->id,
            'first_name' => 'Existing',
            'middle_name' => '',
            'surname' => 'Client',
            'dob' => '1998-03-12',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 777',
            'mobile' => '+61 412 555 777',
            'email' => 'existing.intake@example.test',
            'nationality' => 'Australia',
            'current_location' => 'Melbourne',
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'admin_office' => 'Melbourne',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'notes' => '',
            'primary_counsellor' => 'Old Counsellor',
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Web',
        ]);

        $this
            ->from('/intake/'.$counsellor->id)
            ->post('/intake/'.$counsellor->id, $this->counsellorIntakePayload([
                'first_name' => 'Existing',
                'surname' => 'Client',
                'email' => 'existing.intake@example.test',
                'phone_number' => '412 555 777',
                'dob' => '1998-03-12',
                'current_location' => 'Australia - Other locations',
            ]))
            ->assertRedirect('/intake/'.$counsellor->id.'/thanks');

        $this->assertSame(1, Client::query()->where('email', 'existing.intake@example.test')->count());
        $client->refresh();

        $this->assertSame('Jessica Morgan', $client->primary_counsellor);
        $this->assertSame('Brisbane', $client->admin_office);
        $this->assertSame('1998-03-12', $client->dob?->toDateString());

        $this->assertDatabaseHas('leads', [
            'client_id' => $client->id,
            'assigned_user_id' => $counsellor->id,
            'assigned_office_code' => 'BNE',
            'form_type' => 'counsellor_intake',
        ]);
    }

    public function test_event_registration_form_creates_client_and_event_lead(): void
    {
        $event = PscEvent::create([
            'name' => 'Free Study Pathways Information Session',
            'starts_at' => '2026-11-12 17:30:00',
            'location' => 'PSC Melbourne',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Jessica Morgan',
            'offices' => ['MEL'],
        ]);

        $this->get('/events/'.$event->id.'/register')
            ->assertStatus(200)
            ->assertSee('Registration - Free Study Pathways Information Session')
            ->assertSee('Thursday, 12 November 2026 5:30 PM')
            ->assertSee('PSC Melbourne')
            ->assertSee('Fill in your details to register for this free event.')
            ->assertSee('name="highest_education_level"', false)
            ->assertDontSee('name="captcha"', false);

        $this
            ->from('/events/'.$event->id.'/register')
            ->post('/events/'.$event->id.'/register', $this->eventRegistrationPayload([
                'dob' => '1999-05-20',
                'highest_education_level' => 'Bachelor Degree',
            ]))
            ->assertRedirect('/events/'.$event->id.'/register/thanks');

        $client = Client::query()->where('email', 'kim.event@example.test')->firstOrFail();

        $this->assertSame(1018, (int) $client->client_id);
        $this->assertSame('Prospect', $client->client_status);
        $this->assertSame('Event', $client->tag);
        $this->assertSame('Jessica Morgan', $client->primary_counsellor);
        $this->assertSame('1999-05-20', $client->dob?->toDateString());

        $this->assertDatabaseHas('leads', [
            'lead_id' => 2201,
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'assigned_name' => 'Jessica Morgan',
            'assigned_office_code' => 'MEL',
            'assigned_office' => 'Melbourne',
            'form_type' => 'event_registration',
            'source' => 'Event',
            'psc_event_id' => $event->id,
            'event_name' => 'Free Study Pathways Information Session',
            'email' => 'kim.event@example.test',
            'dob' => '1999-05-20',
            'highest_education_level' => 'Bachelor Degree',
        ]);
    }

    public function test_event_registration_form_links_existing_client_and_accepts_other_education_level(): void
    {
        $oldUser = User::factory()->create(['name' => 'Old Counsellor', 'offices' => ['MEL']]);
        $newUser = User::factory()->create(['name' => 'Priya Raman', 'offices' => ['SYD']]);
        $event = PscEvent::create([
            'name' => 'Student Visa and Admissions Briefing',
            'starts_at' => '2026-12-01 15:00:00',
            'location' => 'Online',
            'active' => true,
        ]);
        $client = Client::create([
            'client_id' => 1018,
            'created_by_user_id' => $oldUser->id,
            'first_name' => 'Existing',
            'middle_name' => '',
            'surname' => 'Client',
            'dob' => '1998-03-12',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 888',
            'mobile' => '+61 412 555 888',
            'email' => 'existing.event@example.test',
            'nationality' => 'Australia',
            'current_location' => 'Melbourne',
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'admin_office' => 'Melbourne',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'notes' => '',
            'primary_counsellor' => 'Old Counsellor',
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Web',
        ]);

        $this
            ->from('/events/'.$event->id.'/register')
            ->post('/events/'.$event->id.'/register', $this->eventRegistrationPayload([
                'first_name' => 'Existing',
                'surname' => 'Client',
                'email' => 'existing.event@example.test',
                'phone_number' => '412 555 888',
                'dob' => '1998-03-12',
                'current_location' => 'Australia - Sydney',
                'highest_education_level' => 'Other / Not specified',
                'highest_education_level_other' => 'Trade certificate',
            ]))
            ->assertRedirect('/events/'.$event->id.'/register/thanks');

        $this->assertSame(1, Client::query()->where('email', 'existing.event@example.test')->count());
        $client->refresh();

        $this->assertSame('Old Counsellor', $client->primary_counsellor);
        $this->assertSame('Sydney', $client->admin_office);
        $this->assertSame('1998-03-12', $client->dob?->toDateString());

        $this->assertDatabaseHas('leads', [
            'client_id' => $client->id,
            'assigned_user_id' => $newUser->id,
            'assigned_office_code' => 'SYD',
            'form_type' => 'event_registration',
            'source' => 'Event',
            'event_name' => 'Student Visa and Admissions Briefing',
            'highest_education_level' => 'Trade certificate',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLeadForList(User $user, array $overrides = []): Lead
    {
        $clientId = (int) ($overrides['client_id'] ?? 2100);
        $firstName = (string) ($overrides['first_name'] ?? 'Lead');
        $middleName = (string) ($overrides['middle_name'] ?? '');
        $surname = (string) ($overrides['surname'] ?? 'Person');
        $email = strtolower($firstName.'.'.$surname.'.'.$clientId).'@example.test';
        $client = Client::create([
            'client_id' => $clientId,
            'created_by_user_id' => $user->id,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'surname' => $surname,
            'dob' => '2000-04-12',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 000',
            'mobile' => '+61 412 555 000',
            'email' => $email,
            'nationality' => 'Philippines',
            'current_location' => (string) ($overrides['assigned_office'] ?? 'Melbourne'),
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'admin_office' => (string) ($overrides['assigned_office'] ?? 'Melbourne'),
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'notes' => '',
            'primary_counsellor' => $user->name,
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Web',
        ]);
        $submittedAt = $overrides['submitted_at'] ?? now()->subDay();

        return Lead::create([
            'lead_id' => (int) ($overrides['lead_id'] ?? 2300),
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'assigned_name' => $user->name,
            'assigned_office_code' => (string) ($overrides['assigned_office_code'] ?? 'MEL'),
            'assigned_office' => (string) ($overrides['assigned_office'] ?? 'Melbourne'),
            'assignment_reason' => 'Test lead assignment',
            'form_type' => 'contact_us',
            'source' => 'Contact us',
            'status' => (string) ($overrides['status'] ?? 'New'),
            'priority' => (string) ($overrides['priority'] ?? 'medium'),
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'surname' => $surname,
            'dob' => '2000-04-12',
            'email' => $email,
            'phone_country_code' => '+61',
            'phone_number' => '412 555 000',
            'mobile' => '+61 412 555 000',
            'nationality' => 'Philippines',
            'current_location' => (string) ($overrides['assigned_office'] ?? 'Melbourne'),
            'highest_education_level' => '',
            'enquiry' => '',
            'submitted_at' => $submittedAt,
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function websiteLeadPayload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Kim',
            'middle_name' => 'Yves',
            'surname' => 'Ramirez',
            'email' => 'kim.website@example.test',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 333',
            'dob' => '1999-05-20',
            'nationality' => 'Philippines',
            'current_location' => 'Australia - Melbourne',
            'enquiry' => '',
            'captcha' => 'ABC123',
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function counsellorIntakePayload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Kim',
            'middle_name' => 'Yves',
            'surname' => 'Ramirez',
            'email' => 'kim.intake@example.test',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 666',
            'dob' => '1999-05-20',
            'nationality' => 'Philippines',
            'current_location' => 'Australia - Melbourne',
            'highest_education_level' => '',
            'enquiry' => '',
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function eventRegistrationPayload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Kim',
            'middle_name' => 'Yves',
            'surname' => 'Ramirez',
            'email' => 'kim.event@example.test',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 999',
            'dob' => '1999-05-20',
            'nationality' => 'Philippines',
            'current_location' => 'Australia - Melbourne',
            'highest_education_level' => '',
            'highest_education_level_other' => '',
        ];
    }
}
