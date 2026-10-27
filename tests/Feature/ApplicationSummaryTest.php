<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationWorkflow;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PSC\ApplicationSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function client(User $user): Client
    {
        return Client::create([
            'client_id' => 5000 + Client::count(), 'first_name' => 'Ana', 'surname' => 'Santos',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => $user->name,
        ]);
    }

    private function application(User $user, array $attributes = []): Application
    {
        return Application::create($attributes + [
            'client_id' => $this->client($user)->id, 'category' => 'Study', 'primary_counsellor_id' => $user->id,
        ]);
    }

    private function fields(Application $application, array $changes = []): array
    {
        return $changes + [
            'status' => $application->status, 'priority' => $application->priority,
            'primary_counsellor_id' => $application->primary_counsellor_id,
            'secondary_counsellor_id' => $application->secondary_counsellor_id,
            'migration_agent_id' => $application->migration_agent_id,
        ];
    }

    public function test_summary_shows_linked_owner_identity_timestamps_and_only_matching_type_fields(): void
    {
        $user = User::factory()->create();
        foreach (Application::TYPES as $category => $label) {
            $application = $this->application($user, [
                'category' => $category, 'psc_client' => false, 'nomination_pathway' => '482',
                'visa_subclass' => '500 - Student Visa', 'assessment_body' => 'Vetassess',
            ]);
            $response = $this->actingAs($user)->get(route('applications.show', $application));
            $response->assertOk()->assertSee('Summary')->assertSee($label)->assertSee('Date Created')
                ->assertSee('Last Modified')->assertSee('View / edit notes')
                ->assertSee('href="'.route('clients.show', $application->client->client_id).'"', false)
                ->assertDontSee('name="category"', false)->assertDontSee('name="client_id"', false);
            foreach (['Visa' => 'visa_subclass', 'Assessment' => 'assessment_body'] as $type => $field) {
                $category === $type ? $response->assertSee('name="'.$field.'"', false) : $response->assertDontSee('name="'.$field.'"', false);
            }
            $category === 'ART Appeal' ? $response->assertSee('PSC Client')->assertSee('No') : $response->assertDontSee('PSC Client');
            $category === 'Nomination' ? $response->assertSee('Nomination subclass') : $response->assertDontSee('Nomination subclass');
        }
    }

    public function test_creation_defaults_and_workflow_selections_survive_loading(): void
    {
        $creator = User::factory()->create();
        $agent = User::factory()->create();
        $workflow = ApplicationWorkflow::create([
            'name' => 'Student visa', 'category' => 'Visa', 'migration_agent_id' => $agent->id,
            'summary_options' => ['visa_subclass' => ['500 - Student Visa', '590 - Student Guardian Visa']],
        ]);
        $application = app(ApplicationSummary::class)->create($creator, [
            'client_id' => $this->client($creator)->id, 'category' => 'Visa',
            'application_workflow_id' => $workflow->id, 'visa_subclass' => '590 - Student Guardian Visa',
        ])->refresh();
        $this->assertSame('Not Started', $application->status);
        $this->assertSame('M', $application->priority);
        $this->assertSame($creator->id, $application->primary_counsellor_id);
        $this->assertSame($agent->id, $application->migration_agent_id);
        $this->assertNull($application->critical_date);
        $this->assertNull($application->secondary_counsellor_id);
        $this->assertNotNull($application->created_at);
        $this->assertSame('590 - Student Guardian Visa', $application->visa_subclass);
        $this->actingAs($creator)->get(route('applications.show', $application))->assertOk()
            ->assertSee('500 - Student Visa')->assertSee('590 - Student Guardian Visa');
    }

    public function test_model_creation_also_defaults_to_authenticated_creator(): void
    {
        $creator = User::factory()->create();
        $this->actingAs($creator);
        $application = Application::create(['client_id' => $this->client($creator)->id, 'category' => 'Study']);
        $this->assertSame($creator->id, $application->primary_counsellor_id);
        $this->assertSame('Not Started', $application->status);
        $this->assertSame('M', $application->priority);
    }

    public function test_edit_persists_fields_clears_optional_values_and_updates_only_modified_timestamp(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->travelTo(now()->startOfSecond());
        $application = $this->application($user);
        $created = $application->created_at->toDateTimeString();
        $this->travel(2)->minutes();
        $this->actingAs($user)->patch(route('applications.update', $application), $this->fields($application, [
            'status' => 'Pending Decision', 'priority' => 'H', 'critical_date' => '2027-02-28',
            'secondary_counsellor_id' => $other->id, 'migration_agent_id' => $other->id,
            'notes' => "Long notes\n<script>alert('unsafe')</script>",
        ]))->assertRedirect(route('applications.show', $application))->assertSessionHas('success');
        $application->refresh();
        $this->assertSame($created, $application->created_at->toDateTimeString());
        $this->assertTrue($application->updated_at->greaterThan($application->created_at));
        $this->assertSame('Pending Decision', $application->status);
        $this->assertSame('H', $application->priority);
        $this->assertSame('2027-02-28', $application->critical_date->format('Y-m-d'));
        $this->assertSame($other->id, $application->secondary_counsellor_id);
        $this->get(route('applications.show', $application))->assertOk()->assertDontSee("<script>alert('unsafe')</script>", false)
            ->assertSee('&lt;script&gt;', false);
        $this->patch(route('applications.update', $application), $this->fields($application, [
            'critical_date' => '', 'secondary_counsellor_id' => '', 'migration_agent_id' => '', 'notes' => '',
        ]))->assertSessionHasNoErrors();
        $application->refresh();
        foreach (['critical_date', 'secondary_counsellor_id', 'migration_agent_id', 'notes'] as $field) {
            $this->assertNull($application->$field);
        }
    }

    public function test_updates_require_access_and_reassignment_redirects_to_list(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $application = $this->application($user, ['status' => 'Finalised']);
        $url = route('applications.update', $application);
        $this->patch($url, [])->assertRedirect(route('login'));
        $this->actingAs($other)->patch($url, $this->fields($application))->assertNotFound();
        $this->actingAs($user)->patch($url, $this->fields($application, ['primary_counsellor_id' => $other->id]))
            ->assertRedirect(route('applications'));
        $this->get(route('applications.show', $application))->assertNotFound();
        $this->actingAs($other)->get(route('applications.show', $application))->assertOk();
    }

    public function test_read_only_and_unrelated_type_fields_cannot_be_forged(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->actingAs($user);
        foreach (['id' => 99, 'client_id' => 99, 'organisation_id' => 99, 'category' => 'Visa',
            'application_workflow_id' => 99, 'created_at' => '2020-01-01', 'updated_at' => '2020-01-01',
            'psc_client' => false, 'nomination_pathway' => '482', 'visa_subclass' => '500', 'assessment_body' => 'Other'] as $field => $value) {
            $this->patchJson(route('applications.update', $application), $this->fields($application, [$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame('Study', $application->fresh()->category);
    }

    public function test_invalid_required_values_dates_and_staff_are_rejected_without_partial_save(): void
    {
        $user = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $restricted = User::factory()->create(['portal_invite_status' => 'Restrict Access']);
        $unknownRole = User::factory()->create(['account_type' => 'External']);
        $application = $this->application($user, ['category' => 'Visa', 'visa_subclass' => '500 - Student Visa']);
        $this->actingAs($user);
        foreach ([['status' => null], ['status' => 'Invalid'], ['priority' => null], ['priority' => 'Z'],
            ['primary_counsellor_id' => null], ['secondary_counsellor_id' => $inactive->id],
            ['secondary_counsellor_id' => $restricted->id], ['secondary_counsellor_id' => $unknownRole->id],
            ['migration_agent_id' => null], ['critical_date' => '2027-02-30'], ['visa_subclass' => null],
            ['visa_subclass' => 'Unconfigured option']] as $invalid) {
            $this->patchJson(route('applications.update', $application), $this->fields($application, $invalid + [
                'migration_agent_id' => $user->id, 'visa_subclass' => '500 - Student Visa', 'notes' => 'Must not save',
            ]))->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->assertNull($application->fresh()->notes);
    }

    public function test_workflow_limits_choices_and_staff_but_preserves_retired_current_choices(): void
    {
        $user = User::factory()->create();
        $allowed = User::factory()->create();
        $outsider = User::factory()->create();
        $workflow = ApplicationWorkflow::create([
            'name' => 'Skills assessment', 'category' => 'Assessment',
            'summary_options' => ['assessment_body' => ['Vetassess', 'Engineers Australia']],
        ]);
        $workflow->caseHandlers()->attach($allowed);
        $application = $this->application($user, [
            'category' => 'Assessment', 'application_workflow_id' => $workflow->id, 'assessment_body' => 'Retired choice',
        ]);
        $this->actingAs($user)->get(route('applications.show', $application))->assertOk()
            ->assertSee('Engineers Australia')->assertSee('Retired choice')->assertSee($allowed->name)->assertDontSee($outsider->name);
        $url = route('applications.update', $application);
        $this->patch($url, $this->fields($application, ['assessment_body' => 'Retired choice']))->assertSessionHasNoErrors();
        $this->patchJson($url, $this->fields($application, ['assessment_body' => 'Vetassess', 'secondary_counsellor_id' => $outsider->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('secondary_counsellor_id');
        $this->patch($url, $this->fields($application, ['assessment_body' => 'Engineers Australia', 'secondary_counsellor_id' => $allowed->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('Engineers Australia', $application->fresh()->assessment_body);
    }

    public function test_failed_form_preserves_valid_input_and_renders_malformed_input_safely(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $url = route('applications.show', $application);
        $this->actingAs($user)->from($url)->patch(route('applications.update', $application), $this->fields($application, [
            'priority' => ['invalid'], 'critical_date' => ['invalid'], 'notes' => ['invalid'],
            'status' => 'Pending Decision',
        ]))->assertRedirect($url)->assertSessionHasErrors(['priority', 'critical_date', 'notes']);
        $this->get($url)->assertOk()->assertSee('The summary could not be saved.')
            ->assertSee('value="Pending Decision" selected', false);
        $this->assertSame('Not Started', $application->fresh()->status);
    }

    public function test_organisation_link_list_export_and_record_are_assignment_scoped(): void
    {
        $user = User::factory()->create();
        $organisation = Organisation::create(['name' => 'Example Employer']);
        $application = $this->application($user, ['client_id' => null, 'organisation_id' => $organisation->id, 'category' => 'Nomination', 'nomination_pathway' => '482']);
        $this->actingAs($user)->get(route('applications.show', $application))->assertOk()->assertSee('Organisation Name')
            ->assertSee('href="'.route('organisations.show', $organisation).'"', false);
        $this->get(route('organisations.show', $organisation))->assertOk()->assertSee('Example Employer');
        $this->get(route('applications'))->assertOk()->assertSee('Example Employer');
        $this->assertStringContainsString('Example Employer', $this->get(route('applications.export'))->streamedContent());
        $this->actingAs(User::factory()->create())->get(route('organisations.show', $organisation))->assertNotFound();
    }

    public function test_creation_validates_owner_workflow_required_type_fields_and_accepts_no_for_psc_client(): void
    {
        $user = User::factory()->create();
        $client = $this->client($user);
        $organisation = Organisation::create(['name' => 'Employer']);
        $workflow = ApplicationWorkflow::create(['name' => 'Nomination', 'category' => 'Nomination', 'summary_options' => ['nomination_pathway' => ['186', '482', '494', 'DAMA']]]);
        $service = app(ApplicationSummary::class);
        $appeal = $service->create($user, ['client_id' => $client->id, 'category' => 'ART Appeal', 'psc_client' => false, 'visa_subclass' => '500 - Student Visa']);
        $this->assertFalse($appeal->fresh()->psc_client);
        $nomination = $service->create($user, ['organisation_id' => $organisation->id, 'category' => 'Nomination', 'application_workflow_id' => $workflow->id, 'nomination_pathway' => 'DAMA']);
        $this->assertSame('DAMA', $nomination->nomination_pathway);
        foreach ([
            ['category' => 'Study'],
            ['category' => 'Study', 'client_id' => $client->id, 'organisation_id' => $organisation->id],
            ['category' => 'ART Appeal', 'client_id' => $client->id],
            ['category' => 'Visa', 'client_id' => $client->id],
            ['category' => 'ART Appeal', 'client_id' => $client->id, 'psc_client' => false],
            ['category' => 'Nomination', 'organisation_id' => $organisation->id, 'application_workflow_id' => $workflow->id],
            ['category' => 'Study', 'client_id' => $client->id, 'application_workflow_id' => $workflow->id],
        ] as $invalid) {
            try {
                $service->create($user, $invalid);
                $this->fail('Invalid application was created.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(2, Application::count());
    }
}
