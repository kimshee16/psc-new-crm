<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
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
            ->assertSee('Melbourne');

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
            'current_location' => 'Melbourne',
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
            'dob' => null,
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
                'email' => 'existing.website@example.test',
                'phone_number' => '412 555 444',
                'current_location' => 'Sydney',
                'captcha' => 'XYZ789',
            ]))
            ->assertRedirect('https://progress-study.com/request-received');

        $this->assertSame(1, Client::query()->where('email', 'existing.website@example.test')->count());
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

    /**
     * @param array<string, string> $overrides
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
            'nationality' => 'Philippines',
            'current_location' => 'Melbourne',
            'enquiry' => '',
            'captcha' => 'ABC123',
        ];
    }
}
