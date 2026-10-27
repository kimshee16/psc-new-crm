<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationWorkflow;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationCreationTest extends TestCase
{
    use RefreshDatabase;

    private function client(User $user): Client
    {
        return Client::create([
            'client_id' => 5500 + Client::count(), 'first_name' => 'PSC33', 'surname' => 'Client',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => $user->name,
        ]);
    }

    private function lead(User $user, Client $client): Lead
    {
        return Lead::create([
            'lead_id' => 3300 + Lead::count(), 'client_id' => $client->id,
            'assigned_user_id' => $user->id, 'assigned_name' => $user->name,
            'assigned_office_code' => 'MEL', 'assigned_office' => 'Melbourne',
            'assignment_reason' => 'Test', 'form_type' => 'manual_client_record', 'source' => 'Manual',
            'status' => 'Consulting', 'prospective_service' => 'Study', 'priority' => 'medium',
            'first_name' => 'PSC33', 'email' => '', 'phone_country_code' => '+61',
            'phone_number' => '', 'mobile' => '', 'nationality' => '', 'current_location' => '', 'submitted_at' => now(),
        ]);
    }

    private function workflow(string $category, array $attributes = []): ApplicationWorkflow
    {
        return ApplicationWorkflow::create($attributes + ['category' => $category, 'name' => $category.' workflow']);
    }

    public function test_client_and_linked_lead_render_the_same_modal_with_documented_choices(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $lead = $this->lead($user, $client);
        foreach ([route('clients.show', $client->client_id), route('leads.show', $lead->lead_id)] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('application-create-dialog')
                ->assertSee('Study and 500 Visa')->assertSee('Study Application')->assertSee('Visa Application')
                ->assertSee('Assessment')->assertSee('ART Appeal')->assertSee('Citizenship Application')
                ->assertSee('Lead only')->assertSee('Search subclass')->assertSee('VETASSESS')
                ->assertSee('500 - Student Visa')->assertSee('143 - Contributory Parent Visa');
        }
    }

    public function test_all_single_types_create_the_correct_owned_record_and_open_it(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $agent = User::factory()->create();
        $visaWorkflow = $this->workflow('Visa', ['migration_agent_id' => $agent->id]);
        $cases = [
            ['category' => 'Study'],
            ['category' => 'Visa', 'visa_subclass' => '590 - Student Guardian Visa', 'application_workflow_id' => $visaWorkflow->id],
            ['category' => 'Assessment', 'assessment_body' => 'ACS'],
            ['category' => 'ART Appeal', 'visa_subclass' => '500 - Student Visa', 'psc_client' => '0'],
            ['category' => 'Citizenship'],
        ];
        foreach ($cases as $index => $data) {
            $response = $this->actingAs($user)->post(route('clients.applications.create', $client->client_id), $data + [
                'client_id' => 999999, 'organisation_id' => 999999, 'primary_counsellor_id' => $agent->id,
            ]);
            $application = Application::latest('id')->first();
            $response->assertSessionHasNoErrors()->assertRedirect(route('applications.show', $application));
            $this->assertDatabaseCount('applications', $index + 1);
            $this->assertSame($data['category'], $application->category);
            $this->assertSame($client->id, $application->client_id);
            $this->assertNull($application->organisation_id);
            $this->assertSame($user->id, $application->primary_counsellor_id);
            $this->assertSame('M', $application->priority);
            $this->assertSame('Not Started', $application->status);
            $this->get(route('applications.show', $application))->assertOk()->assertSee($application->type_label);
            $this->get(route('clients.show', $client->client_id))->assertSee('Application ID '.$application->id);
        }
        $this->assertFalse(Application::where('category', 'ART Appeal')->first()->psc_client);
        $this->assertSame($agent->id, Application::where('category', 'Visa')->first()->migration_agent_id);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_combined_creation_links_both_records_and_converts_the_source_lead_once(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $lead = $this->lead($user, $client);
        $studyWorkflow = $this->workflow('Study');
        $visaWorkflow = $this->workflow('Visa', ['migration_agent_id' => $user->id]);
        $input = ['category' => 'Study and 500 Visa', 'application_workflow_id' => $studyWorkflow->id, 'visa_workflow_id' => $visaWorkflow->id];
        $url = route('leads.applications.create', $lead->lead_id);
        $response = $this->actingAs($user)->post($url, $input);
        $study = Application::where('category', 'Study')->firstOrFail();
        $visa = Application::where('category', 'Visa')->firstOrFail();
        $response->assertSessionHasNoErrors()->assertRedirect(route('applications.show', $study));
        $this->assertSame($visa->id, $study->linked_application_id);
        $this->assertSame($study->id, $visa->linked_application_id);
        $this->assertSame('500 - Student Visa', $visa->visa_subclass);
        $this->assertSame('Converted', $lead->fresh()->status);
        foreach ([$study, $visa] as $application) {
            $this->assertSame($client->id, $application->client_id);
            $this->assertSame($lead->id, $application->source_lead_id);
            $this->get(route('applications.show', $application))->assertOk()
                ->assertSee('href="'.route('applications.show', $application->linked_application_id).'"', false);
        }
        $this->get(route('clients.show', $client->client_id))->assertSee('Application ID '.$study->id)
            ->assertSee('Application ID '.$visa->id)->assertDontSee('Lead ID '.$lead->lead_id);
        $this->post($url, $input)->assertRedirect(route('applications.show', $study));
        $this->assertDatabaseCount('applications', 2);
    }

    public function test_failure_of_second_record_rolls_back_first_record_and_lead_conversion(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $lead = $this->lead($user, $client);
        $workflow = $this->workflow('Visa', [
            'migration_agent_id' => $user->id, 'summary_options' => ['visa_subclass' => ['590 - Student Guardian Visa']],
        ]);
        $this->actingAs($user)->post(route('leads.applications.create', $lead->lead_id), [
            'category' => 'Study and 500 Visa', 'visa_workflow_id' => $workflow->id,
        ])->assertSessionHasErrors('visa_subclass');
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame('Consulting', $lead->fresh()->status);
    }

    public function test_required_conditional_fields_templates_and_eligible_agents_are_enforced(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $workflow = $this->workflow('Study');
        $url = route('clients.applications.create', $client->client_id);
        foreach ([
            [[], ['category']],
            [['category' => 'Nomination'], ['category']],
            [['category' => 'SBS'], ['category']],
            [['category' => 'Study'], ['application_workflow_id']],
            [['category' => 'Visa', 'application_workflow_id' => $workflow->id, 'visa_subclass' => '500 - Student Visa'], ['application_workflow_id']],
            [['category' => 'Visa'], ['visa_subclass']],
            [['category' => 'Visa', 'visa_subclass' => 'invented', 'migration_agent_id' => $user->id], ['visa_subclass']],
            [['category' => 'Visa', 'visa_subclass' => '500 - Student Visa', 'migration_agent_id' => 999999], ['migration_agent_id']],
            [['category' => 'ART Appeal', 'psc_client' => '0'], ['visa_subclass']],
            [['category' => 'ART Appeal', 'visa_subclass' => '500 - Student Visa'], ['psc_client']],
            [['category' => 'Assessment', 'assessment_body' => 'invented'], ['assessment_body']],
        ] as [$input, $errors]) {
            $this->actingAs($user)->post($url, $input)->assertSessionHasErrors($errors);
        }
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_optional_assessment_and_configured_extensions_work_and_irrelevant_values_are_discarded(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $url = route('clients.applications.create', $client->client_id);
        $this->actingAs($user)->post($url, ['category' => 'Assessment'])->assertSessionHasNoErrors();
        $this->assertNull(Application::first()->assessment_body);
        config(['applications.creation_options.visa_subclass' => ['Custom subclass']]);
        $this->post($url, ['category' => 'Visa', 'visa_subclass' => 'Custom subclass', 'migration_agent_id' => $user->id])->assertSessionHasNoErrors();
        $this->post($url, ['category' => 'Study', 'visa_subclass' => 'invented', 'psc_client' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(Application::where('category', 'Study')->first()->visa_subclass);
        $this->assertNull(Application::where('category', 'Study')->first()->psc_client);
    }

    public function test_lead_only_creates_a_lead_from_client_and_retains_an_existing_lead(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $this->actingAs($user)->post(route('clients.applications.create', $client->client_id), [
            'category' => 'ART Appeal', 'create_lead' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('clients.show', $client->client_id));
        $lead = Lead::firstOrFail();
        $this->assertSame('ART Appeal', $lead->prospective_service);
        $this->post(route('leads.applications.create', $lead->lead_id), [
            'category' => 'Study and 500 Visa', 'create_lead' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('leads.show', $lead->lead_id));
        $this->assertSame('New', $lead->fresh()->status);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_lead_access_and_source_client_are_enforced_and_failed_forms_reopen_safely(): void
    {
        $user = User::factory()->create(['account_type' => 'Counsellor']);
        $other = User::factory()->create(['account_type' => 'Counsellor']);
        $client = $this->client($user);
        $lead = $this->lead($user, $client);
        $this->actingAs($other)->post(route('leads.applications.create', $lead->lead_id), ['category' => 'Study'])->assertNotFound();
        $this->actingAs($user)->post(route('clients.applications.create', 999999), ['category' => 'Study'])->assertNotFound();
        $url = route('clients.show', $client->client_id);
        $this->from($url)->post(route('clients.applications.create', $client->client_id), [
            '_create_application' => '1', 'category' => 'ART Appeal', 'psc_client' => '0', 'visa_subclass' => ['invalid'],
        ])->assertRedirect($url)->assertSessionHasErrors('visa_subclass');
        $this->get($url)->assertOk()->assertSee('The application could not be created.')->assertSee('dialog.showModal()');
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_linked_records_and_closed_records_do_not_leak_through_client_or_application_views(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $this->actingAs($user)->post(route('clients.applications.create', $client->client_id), [
            'category' => 'Study and 500 Visa', 'migration_agent_id' => $user->id,
        ])->assertSessionHasNoErrors();
        $study = Application::where('category', 'Study')->firstOrFail();
        $visa = Application::where('category', 'Visa')->firstOrFail();
        $visa->update(['primary_counsellor_id' => User::factory()->create()->id, 'migration_agent_id' => null]);
        $this->get(route('applications.show', $study))->assertDontSee('Linked application');
        $this->get(route('applications.show', $visa))->assertNotFound();
        $study->update(['status' => 'Finalised']);
        $this->get(route('clients.show', $client->client_id))->assertDontSee('Application ID '.$study->id)->assertDontSee('Application ID '.$visa->id);
    }
}
