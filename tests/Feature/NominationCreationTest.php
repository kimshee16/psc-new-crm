<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Client;
use App\Models\NominationSubclass;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PSC\NominationCreation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NominationCreationTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(User $user): Organisation
    {
        return Organisation::create(['name' => 'Acme Legal Pty Ltd', 'trading_name' => 'Acme Trading', 'created_by_user_id' => $user->id]);
    }

    private function client(): Client
    {
        return Client::create(['client_id' => 8001, 'first_name' => 'Ana', 'middle_name' => 'Maria', 'surname' => 'Santos',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => 'Other Counsellor']);
    }

    public function test_organisation_has_modal_and_blank_required_choices(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user)->get(route('organisations.show', $organisation))->assertOk()
            ->assertSee('id="create-nomination"', false)->assertSee('<dialog id="nomination-create-dialog"', false)
            ->assertSee('Create a nomination')->assertSee('Acme Legal Pty Ltd')->assertSee('Acme Trading')
            ->assertSee('Search client by name or ID')->assertSee('External: enter the nominee’s full name instead')
            ->assertSee('<option value="">Select subclass</option>', false)->assertSee('Cancel');
    }

    public function test_internal_nominee_resolves_public_id_without_sbs_or_position_title(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $client = $this->client();
        $this->actingAs($user)->post(route('organisations.applications.store', $organisation), [
            'category' => 'Nomination', 'nominee_client_reference' => $client->client_id,
            'nominee_name' => 'Forged display name', 'visa_subclass' => '482 - Skills in Demand (SID) Visa',
            'client_id' => $client->id, 'status' => 'Finalised', 'primary_counsellor_id' => 999,
        ])->assertSessionHasNoErrors()->assertRedirect(route('applications.show', Application::sole()));
        $application = Application::sole();
        $this->assertSame($client->id, $application->nominee_client_id);
        $this->assertNull($application->nominee_name);
        $this->assertNull($application->client_id);
        $this->assertNull($application->nominated_position_title);
        $this->assertSame($organisation->id, $application->organisation_id);
        $this->assertSame('482 - Skills in Demand (SID) Visa', $application->nomination_pathway);
        $this->assertSame('Not Started', $application->status);
        $this->assertSame('M', $application->priority);
        $this->assertSame($user->id, $application->primary_counsellor_id);
        $this->get(route('applications.show', $application))->assertOk()->assertSee('Ana Maria Santos')
            ->assertSee('482 - Skills in Demand (SID) Visa');
    }

    public function test_external_nominee_is_saved_without_a_client_and_stale_selection_is_discarded(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $client = $this->client();
        $this->actingAs($user)->post(route('organisations.applications.store', $organisation), [
            'category' => 'Nomination', 'external_nominee' => '1', 'nominee_name' => '  External Nominee  ',
            'nominee_client_reference' => $client->client_id, 'visa_subclass' => '186 - Employer Nomination Scheme Visa',
        ])->assertSessionHasNoErrors();
        $application = Application::sole();
        $this->assertNull($application->nominee_client_id);
        $this->assertSame('External Nominee', $application->nominee_name);
        $this->get(route('organisations.show', $organisation))->assertSee('External Nominee');
    }

    public function test_nominee_and_configured_subclass_are_mandatory_for_every_submission(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user);
        $valid = ['category' => 'Nomination', 'external_nominee' => '1', 'nominee_name' => 'External Nominee',
            'visa_subclass' => '494 - Skilled Employer Sponsored Regional Visa'];
        foreach ([
            [['external_nominee' => '0'], 'nominee_client_reference'],
            [['nominee_name' => '   '], 'nominee_name'],
            [['nominee_name' => str_repeat('A', 256)], 'nominee_name'],
            [['external_nominee' => 'invalid'], 'external_nominee'],
            [['external_nominee' => '0', 'nominee_client_reference' => 999999], 'nominee_client_reference'],
            [['visa_subclass' => ''], 'visa_subclass'],
            [['visa_subclass' => 'invented'], 'visa_subclass'],
            [['visa_subclass' => ['482']], 'visa_subclass'],
            [['nomination_pathway' => 'forged'], 'nomination_pathway'],
        ] as [$changes, $field]) {
            $this->postJson(route('organisations.applications.store', $organisation), array_replace($valid, $changes))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson(route('organisations.applications.store', $organisation), [
            'category' => 'Nomination', 'nominee_name' => 'Typed without external checkbox', 'visa_subclass' => $valid['visa_subclass'],
        ])->assertUnprocessable()->assertJsonValidationErrors('nominee_client_reference');
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_nominee_lookup_searches_names_and_public_id_and_limits_returned_data(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $client = $this->client();
        $this->actingAs($user);
        foreach (['ana', 'Maria', 'Santos', 'Ana Santos', 'santos ana', '8001'] as $term) {
            $this->getJson(route('organisations.nominees', ['organisation' => $organisation, 'q' => $term]))
                ->assertOk()->assertExactJson(['clients' => [['client_id' => $client->client_id, 'name' => 'Ana Maria Santos']]]);
        }
        $this->getJson(route('organisations.nominees', ['organisation' => $organisation, 'q' => 'No match']))
            ->assertOk()->assertExactJson(['clients' => []]);
        foreach (['', ['bad'], str_repeat('a', 256)] as $term) {
            $this->getJson(route('organisations.nominees', ['organisation' => $organisation, 'q' => $term]))->assertUnprocessable();
        }
    }

    public function test_subclass_configuration_drives_display_and_validation_without_sbs_rules(): void
    {
        NominationSubclass::query()->delete();
        NominationSubclass::create(['subclass' => '407', 'sbs_required' => true]);
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user)->get(route('organisations.show', $organisation))->assertSee('407 - Training Visa');
        $payload = ['category' => 'Nomination', 'external_nominee' => '1', 'nominee_name' => 'External'];
        $this->postJson(route('organisations.applications.store', $organisation), $payload + [
            'visa_subclass' => '482 - Skills in Demand (SID) Visa',
        ])->assertUnprocessable();
        $this->post(route('organisations.applications.store', $organisation), $payload + [
            'visa_subclass' => '407 - Training Visa',
        ])->assertSessionHasNoErrors();
        $this->assertSame('407 - Training Visa', Application::sole()->nomination_pathway);
    }

    public function test_nomination_creation_does_not_depend_on_sponsorship_status_or_expiry_for_any_configured_subclass(): void
    {
        $subclasses = app(NominationCreation::class)->subclasses();
        $user = User::factory()->create();
        $client = $this->client();
        $this->actingAs($user);
        foreach ([null, 'Not Started', 'Active', 'Expired'] as $status) {
            $organisation = $this->organisation($user);
            if ($status !== null) {
                $organisation->applications()->create([
                    'category' => 'SBS', 'sponsor_type' => 'Standard Business Sponsor', 'status' => $status,
                    'expiry_date' => '2020-01-01', 'primary_counsellor_id' => $user->id,
                ]);
            }
            $this->get(route('organisations.show', $organisation))->assertOk()
                ->assertSee('id="create-nomination"', false)
                ->assertSee('<option value="">Select subclass</option>', false);
            foreach ($subclasses as $subclass) {
                foreach ([false, true] as $external) {
                    $this->post(route('organisations.applications.store', $organisation), [
                        'category' => 'Nomination', 'visa_subclass' => $subclass,
                    ] + ($external ? ['external_nominee' => '1', 'nominee_name' => 'External Nominee']
                        : ['nominee_client_reference' => $client->client_id]))->assertSessionHasNoErrors()->assertRedirect();
                    $nomination = $organisation->applications()->where('category', 'Nomination')->latest('id')->firstOrFail();
                    $this->assertSame($subclass, $nomination->nomination_pathway);
                    $this->assertSame('Not Started', $nomination->status);
                    $this->assertSame($external ? null : $client->id, $nomination->nominee_client_id);
                    $this->assertSame($external ? 'External Nominee' : null, $nomination->nominee_name);
                }
            }
            $this->assertSame(8, $organisation->applications()->where('category', 'Nomination')->count());
        }
    }

    public function test_modal_uses_only_the_configured_catalogue_and_does_not_preselect_a_subclass(): void
    {
        NominationSubclass::query()->whereNotIn('subclass', ['482', '186'])->delete();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('organisations.show', $this->organisation($user)))->assertOk();
        $response->assertViewHas('nominationSubclasses', ['482 - Skills in Demand (SID) Visa', '186 - Employer Nomination Scheme Visa'])
            ->assertSee('<option value="">Select subclass</option>', false)
            ->assertSee('<option value="482 - Skills in Demand (SID) Visa">482 - Skills in Demand (SID) Visa</option>', false)
            ->assertDontSee('500 - Student Visa');
    }

    public function test_validation_redirect_preserves_draft_and_reopens_modal(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $client = $this->client();
        $url = route('organisations.show', $organisation);
        $this->actingAs($user)->from($url)->post(route('organisations.applications.store', $organisation), [
            '_create_nomination' => '1', 'category' => 'Nomination', 'nominee_client_reference' => $client->client_id,
            'visa_subclass' => '',
        ])->assertRedirect($url)->assertSessionHasErrors('visa_subclass')->assertSessionHasInput('nominee_client_reference', $client->client_id);
        $this->get($url)->assertOk()->assertSee('The nomination could not be created.')
            ->assertSee('Ana Maria Santos')->assertViewHas('selectedNominee', fn ($selected) => $selected->is($client));
    }

    public function test_creation_and_search_enforce_organisation_and_authentication_access(): void
    {
        $owner = User::factory()->create();
        $organisation = $this->organisation($owner);
        $search = route('organisations.nominees', ['organisation' => $organisation, 'q' => 'Ana']);
        $store = route('organisations.applications.store', $organisation);
        $this->getJson($search)->assertUnauthorized();
        $this->postJson($store, [])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($search)->assertNotFound();
        $this->postJson($store, ['category' => 'Nomination'])->assertNotFound();
        $this->actingAs(User::factory()->create(['is_active' => false]))->get($search)->assertRedirect(route('login'));
        $this->assertDatabaseCount('applications', 0);
    }
}
