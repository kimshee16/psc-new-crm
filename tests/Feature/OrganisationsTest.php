<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\ApplicationTypeSetting;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PSC\ApplicationLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationsTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(User $user, string $name = 'Example Employer'): Organisation
    {
        return Organisation::create(['name' => $name, 'created_by_user_id' => $user->id]);
    }

    private function application(Organisation $organisation, User $user, string $category = 'SBS', array $data = []): Application
    {
        return $organisation->applications()->create($data + ['category' => $category, 'primary_counsellor_id' => $user->id]);
    }

    private function client(): Client
    {
        return Client::create(['client_id' => 8001, 'first_name' => 'Ana', 'surname' => 'Santos',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => 'Counsellor']);
    }

    public function test_organisation_creation_editing_and_search_are_independent_of_clients(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('organisations.create'))->assertOk()->assertSee('Legal Name');
        $this->post(route('organisations.store'), [
            'legal_name' => 'Acme Pty Ltd', 'trading_name' => 'Acme', 'abn' => '51 824 753 556',
            'address' => "10 Main Street\nMelbourne VIC 3000", 'created_by_user_id' => 999,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $organisation = Organisation::sole();
        $this->assertSame($user->id, $organisation->created_by_user_id);
        $this->assertSame('51824753556', $organisation->abn);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->get(route('organisations.show', $organisation))->assertOk()->assertSee($organisation->reference)
            ->assertSee('Acme Pty Ltd')->assertSee('Contact persons')->assertSee('Add sponsorship')->assertSee('Create nomination');
        foreach (['Acme', '51824753556', $organisation->reference] as $search) {
            $this->get(route('organisations', ['search' => $search]))->assertOk()->assertSee('Acme Pty Ltd');
        }
        $this->get(route('organisations.edit', $organisation))->assertOk();
        $this->patch(route('organisations.update', $organisation), ['legal_name' => 'New Legal Name', 'trading_name' => null,
            'abn' => null, 'address' => null])->assertSessionHasNoErrors()->assertRedirect(route('organisations.show', $organisation));
        $this->assertSame('New Legal Name', $organisation->fresh()->name);
        $this->get(route('organisations', ['search' => 'nonexistent']))->assertSee('No organisations found.');
    }

    public function test_contacts_are_persisted_editable_and_scoped_to_their_organisation(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $other = $this->organisation($user, 'Other');
        $this->actingAs($user)->post(route('organisations.contacts.store', $organisation), [
            'name' => 'Pat Manager', 'email' => 'pat@example.test', 'phone' => '+61 400 123 456', 'role' => 'HR Manager',
            'organisation_id' => $other->id,
        ])->assertSessionHasNoErrors();
        $contact = $organisation->contacts()->sole();
        $this->get(route('organisations.show', $organisation))->assertOk()->assertSee('pat@example.test')->assertSee('HR Manager');
        $this->patch(route('organisations.contacts.update', [$organisation, $contact]), ['name' => 'Pat Director', 'role' => 'Director'])->assertSessionHasNoErrors();
        $this->assertSame('Pat Director', $contact->fresh()->name);
        $this->patch(route('organisations.contacts.update', [$other, $contact]), ['name' => 'Wrong'])->assertNotFound();
        $this->assertSame('Pat Director', $contact->fresh()->name);
    }

    public function test_sbs_creation_and_details_are_reflected_in_organisation_table(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user)->get(route('organisations.applications.create', ['organisation' => $organisation, 'category' => 'SBS']))
            ->assertRedirect(route('organisations.show', ['organisation' => $organisation, 'add_sponsorship' => 1]));
        $this->post(route('organisations.applications.store', $organisation), [
            'category' => 'SBS', 'sponsor_type' => 'Standard Business Sponsor', 'approval_date' => '2026-10-01',
            'expiry_date' => '2031-10-01', 'home_affairs_reference' => 'HA-123', 'trn' => 'ABC1234567890',
            'client_id' => 999, 'organisation_id' => 999, 'primary_counsellor_id' => 999,
        ])->assertSessionHasNoErrors();
        $application = Application::sole();
        $this->assertSame($organisation->id, $application->organisation_id);
        $this->assertNull($application->client_id);
        $this->assertSame($user->id, $application->primary_counsellor_id);
        $this->get(route('applications.show', $application))->assertOk()->assertSee('Sponsorship')->assertSee('ABC1234567890');
        $this->get(route('applications.organisation-details.edit', $application))->assertOk()->assertSee('ABC1234567890');
        $this->patch(route('applications.organisation-details.update', $application), [
            'trn' => 'XYZ1234567890', 'outcome' => 'Approved', 'outcome_date' => '2026-10-02', 'expiry_date' => '2031-10-02',
        ])->assertSessionHasNoErrors();
        $this->get(route('organisations.show', $organisation))->assertOk()->assertSee('Standard Business Sponsor')
            ->assertSee('02 Oct 2031')->assertSee('XYZ1234567890')->assertSee($user->name)
            ->assertSee('href="'.route('applications.show', $application).'"', false);
    }

    public function test_nomination_supports_unlinked_nominee_and_resolves_public_client_id_and_live_visa_links(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $client = $this->client();
        $visa = Application::create(['client_id' => $client->id, 'category' => 'Visa', 'primary_counsellor_id' => $user->id, 'status' => 'In Progress']);
        $this->actingAs($user)->get(route('organisations.applications.create', ['organisation' => $organisation, 'category' => 'Nomination']))
            ->assertRedirect(route('organisations.show', ['organisation' => $organisation, 'create_nomination' => 1]));
        $this->post(route('organisations.applications.store', $organisation), [
            'category' => 'Nomination', 'nominated_position_title' => 'Chef', 'nominee_name' => 'Future Nominee',
            'external_nominee' => '1', 'visa_subclass' => '482 - Skills in Demand (SID) Visa',
        ])->assertSessionHasNoErrors();
        $unlinked = $organisation->applications()->sole();
        $this->assertNull($unlinked->nominee_client_id);
        $this->post(route('organisations.applications.store', $organisation), [
            'category' => 'Nomination', 'nominated_position_title' => 'Engineer', 'nominee_client_reference' => 8001,
            'visa_subclass' => '186 - Employer Nomination Scheme Visa',
            'outcome' => 'Approved', 'outcome_date' => '2026-11-01', 'visa_application_id' => $visa->id,
        ])->assertSessionHasNoErrors();
        $nomination = $organisation->applications()->latest('id')->firstOrFail();
        $this->assertSame($client->id, $nomination->nominee_client_id);
        $this->assertNull($nomination->client_id);
        $this->get(route('organisations.show', $organisation))->assertOk()->assertSee('Future Nominee')
            ->assertSee('Ana Santos')->assertSee('Approved')->assertSee('In Progress')
            ->assertSee('href="'.route('clients.show', 8001).'"', false)->assertSee('href="'.route('applications.show', $visa).'"', false);
        $visa->update(['status' => 'Granted']);
        $this->get(route('organisations.show', $organisation))->assertSee('Granted');
        $this->get(route('applications.organisation-details.edit', $nomination))->assertOk()->assertSee('Client 8001')->assertSee('Nominee and subclass are locked');
        app(ApplicationLinks::class)->change($user, $nomination->id, $visa->id, false);
        $this->get(route('organisations.show', $organisation))->assertDontSee('href="'.route('applications.show', $visa).'"', false);
    }

    public function test_all_four_tabs_collate_only_assigned_sbs_and_nomination_records_for_this_organisation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $organisation = $this->organisation($user);
        $sbs = $this->application($organisation, $user);
        $nomination = $this->application($organisation, $user, 'Nomination');
        $hidden = $this->application($organisation, $other);
        $study = $this->application($organisation, $user, 'Study');
        $unrelated = $this->application($this->organisation($user, 'Other employer'), $user);
        $this->actingAs($user);
        foreach (array_intersect_key(ApplicationActivity::TABS, ApplicationTypeSetting::tabsFor('Nomination'), ApplicationTypeSetting::tabsFor('SBS')) as $kind => $label) {
            foreach ([$sbs, $nomination] as $application) {
                $this->post(route('applications.activities.store', $application), [
                    'kind' => $kind, 'title' => $label.' for '.$application->category, 'status' => 'Open',
                    'reference' => 'REF-123', 'due_date' => '2026-12-01',
                ] + (in_array($kind, ['payments', 'quotes']) ? ['amount' => '1250.75', 'currency' => 'AUD'] : []))->assertSessionHasNoErrors();
                $this->get(route('applications.show', ['application' => $application, 'tab' => $kind]))->assertOk()->assertSee($label.' for '.$application->category);
            }
            foreach ([$hidden, $study, $unrelated] as $application) {
                $application->activities()->create(['kind' => $kind, 'title' => 'Hidden activity '.$application->id]);
            }
            $response = $this->get(route('organisations.show', ['organisation' => $organisation, 'tab' => $kind]))
                ->assertOk()->assertSee($label.' for SBS')->assertSee($label.' for Nomination')
                ->assertDontSee('Hidden activity')->assertViewHas('activities', fn ($rows) => $rows->total() === 2)
                ->assertViewHas('counts', fn ($counts) => $counts[$kind] === 2);
            if (in_array($kind, ['payments', 'quotes'])) {
                $response->assertSee('AUD 1,250.75');
            }
        }
        foreach (['unknown', ['tasks']] as $tab) {
            $this->get(route('organisations.show', ['organisation' => $organisation, 'tab' => $tab]))->assertOk()->assertViewHas('activeTab', 'tasks');
        }
    }

    public function test_assignment_access_is_preserved_including_hidden_visa_links(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $organisation = $this->organisation($owner);
        $nomination = $this->application($organisation, $owner, 'Nomination');
        $visa = $this->application($organisation, $owner, 'Visa', ['status' => 'Granted']);
        app(ApplicationLinks::class)->change($owner, $nomination->id, $visa->id, true);
        $this->actingAs($user)->get(route('organisations'))->assertDontSee('Example Employer');
        $this->get(route('organisations.show', $organisation))->assertNotFound();
        $this->get(route('organisations.edit', $organisation))->assertNotFound();
        $this->patch(route('organisations.update', $organisation), ['legal_name' => 'Wrong'])->assertNotFound();
        $this->post(route('organisations.contacts.store', $organisation), ['name' => 'Wrong'])->assertNotFound();
        $this->post(route('organisations.applications.store', $organisation), ['category' => 'SBS', 'sponsor_type' => 'Wrong'])->assertNotFound();
        $this->post(route('applications.activities.store', $nomination), ['kind' => 'tasks', 'title' => 'Wrong'])->assertNotFound();
        $this->patch(route('applications.organisation-details.update', $nomination), ['nominated_position_title' => 'Wrong'])->assertNotFound();
        $nomination->update(['secondary_counsellor_id' => $user->id]);
        $this->get(route('organisations.show', $organisation))->assertOk()->assertDontSee('Granted')
            ->assertDontSee('href="'.route('applications.show', $visa).'"', false);
        $nomination->update(['secondary_counsellor_id' => null]);
        $this->get(route('organisations.show', $organisation))->assertNotFound();
    }

    public function test_invalid_data_is_rejected_without_partial_records(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user);
        $this->postJson(route('organisations.store'), ['legal_name' => '', 'abn' => 'invalid'])->assertUnprocessable();
        $this->postJson(route('organisations.contacts.store', $organisation), ['name' => 'Pat', 'email' => 'invalid'])->assertUnprocessable();
        foreach ([
            ['category' => 'Study'],
            ['category' => 'SBS', 'sponsor_type' => 'Standard', 'approval_date' => '2026-10-02', 'expiry_date' => '2026-10-01'],
            ['category' => 'SBS', 'sponsor_type' => 'Standard', 'approval_date' => '2026-02-30'],
            ['category' => 'Nomination', 'nominated_position_title' => 'Chef', 'nominee_client_reference' => 9999],
            ['category' => 'Nomination', 'nominated_position_title' => 'Chef', 'visa_application_id' => 9999],
            ['category' => 'Nomination', 'nominated_position_title' => 'Chef', 'trn' => 'Not allowed'],
        ] as $payload) {
            $this->postJson(route('organisations.applications.store', $organisation), $payload)->assertUnprocessable();
        }
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('organisation_contacts', 0);
        $sbs = $this->application($organisation, $user);
        foreach ([
            ['kind' => 'payments', 'title' => 'Fee', 'amount' => '-1', 'currency' => 'AUD'],
            ['kind' => 'quotes', 'title' => 'Fee', 'amount' => '1.001', 'currency' => 'AUD'],
            ['kind' => 'tasks', 'title' => 'Task', 'amount' => '10'],
            ['kind' => 'unknown', 'title' => 'Wrong'],
        ] as $payload) {
            $this->postJson(route('applications.activities.store', $sbs), $payload)->assertUnprocessable();
        }
        ApplicationTypeSetting::create(['category' => 'SBS', 'tabs' => []]);
        $this->postJson(route('applications.activities.store', $sbs), ['kind' => 'tasks', 'title' => 'Disabled'])->assertUnprocessable();
        $this->assertDatabaseCount('application_activities', 0);
    }

    public function test_guest_and_inactive_users_cannot_access_organisation_routes(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->get(route('organisations'))->assertRedirect(route('login'));
        $this->post(route('organisations.store'), ['legal_name' => 'Guest'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get(route('organisations.show', $organisation))->assertRedirect(route('login'));
    }

    public function test_partial_updates_preserve_nominee_and_validate_the_saved_date_pair(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $client = $this->client();
        $nomination = $this->application($organisation, $user, 'Nomination', ['nominee_client_id' => $client->id]);
        $sbs = $this->application($organisation, $user, 'SBS', ['sponsor_type' => 'Standard',
            'approval_date' => '2026-10-01', 'expiry_date' => '2026-11-01']);
        $this->actingAs($user)->patch(route('applications.organisation-details.update', $nomination), [
            'nominated_position_title' => 'Engineer',
        ])->assertSessionHasNoErrors();
        $this->assertSame($client->id, $nomination->fresh()->nominee_client_id);
        $this->patchJson(route('applications.organisation-details.update', $sbs), [
            'sponsor_type' => 'Standard', 'approval_date' => '2026-12-01',
        ])->assertUnprocessable();
        $this->assertSame('2026-10-01', $sbs->fresh()->approval_date->format('Y-m-d'));
        $this->patchJson(route('applications.organisation-details.update', $nomination), [
            'nominated_position_title' => 'Engineer', 'nominee_client_reference' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('nominee_client_reference');
        $this->assertSame($client->id, $nomination->fresh()->nominee_client_id);
    }

    public function test_reverse_and_legacy_visa_links_are_collated_without_duplicates(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $nomination = $this->application($organisation, $user, 'Nomination');
        $visa = $this->application($organisation, $user, 'Visa');
        $visa->linked_application_id = $nomination->id;
        $visa->save();
        app(ApplicationLinks::class)->change($user, $visa->id, $nomination->id, true);
        $this->actingAs($user)->get(route('organisations.show', $organisation))->assertOk()
            ->assertViewHas('nominations', fn ($rows) => $rows->first()->linkedVisas->pluck('id')->all() === [$visa->id]);
    }

    public function test_nomination_rejects_unassigned_visa_and_non_visa_targets_atomically(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $hiddenVisa = $this->application($organisation, User::factory()->create(), 'Visa');
        $sbs = $this->application($organisation, $user);
        foreach ([$hiddenVisa, $sbs] as $target) {
            $this->actingAs($user)->postJson(route('organisations.applications.store', $organisation), [
                'category' => 'Nomination', 'nominated_position_title' => 'Chef', 'visa_application_id' => $target->id,
                'external_nominee' => '1', 'nominee_name' => 'External', 'visa_subclass' => '482 - Skills in Demand (SID) Visa',
            ])->assertUnprocessable()->assertJsonValidationErrors('visa_application_id');
        }
        $this->assertDatabaseCount('applications', 2);
        $this->assertDatabaseCount('application_links', 0);
    }
}
