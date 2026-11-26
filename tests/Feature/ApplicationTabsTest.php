<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\ApplicationCatalogEntry;
use App\Models\ApplicationTypeSetting;
use App\Models\ApplicationWorkflow;
use App\Models\Checkpoint;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTabsTest extends TestCase
{
    use RefreshDatabase;

    private function application(User $user, string $category = 'Study'): Application
    {
        return Application::create([
            'organisation_id' => Organisation::create(['name' => 'Test organisation'])->id,
            'category' => $category,
            'primary_counsellor_id' => $user->id,
        ]);
    }

    public function test_catalogs_limit_assignments_and_only_show_program_options_when_assigned(): void
    {
        $admin = User::factory()->create(['account_type' => 'Admin']);
        $study = $this->application($admin);
        $visa = $this->application($admin, 'Visa');
        $route = route('configuration.application-types.update');

        $this->actingAs($admin)->get(route('configuration', ['tab' => 'actions']))
            ->assertOk()->assertSee('Action name')->assertSee('Send Service Agreement');
        $this->get(route('configuration', ['tab' => 'modules']))
            ->assertOk()->assertSee('Module name')->assertSee('Program Options');
        $this->get(route('applications.show', $study))->assertDontSee('program-options-heading');
        $this->patchJson($route, ['category' => 'Study', 'mode' => 'type', 'modules' => ['Unknown']])
            ->assertUnprocessable()->assertJsonValidationErrors('modules.0');
        $this->patch($route, ['category' => 'Study', 'mode' => 'type', 'modules' => ['Program Options']])
            ->assertSessionHasNoErrors();
        $this->get(route('applications.show', $study))->assertOk()->assertSee('program-options-heading');
        $this->get(route('applications.show', $visa))->assertOk()->assertDontSee('program-options-heading');
    }

    public function test_documents_require_configuration_access_and_a_valid_type(): void
    {
        $admin = User::factory()->create(['account_type' => 'Admin']);
        $staff = User::factory()->create(['account_type' => 'Staff']);
        $route = route('configuration.documents.store');

        $this->actingAs($staff)->post($route, ['type' => 'Identity', 'name' => 'Birth certificate'])->assertForbidden();
        $this->actingAs($admin)->postJson($route, ['type' => 'Unknown', 'name' => 'Birth certificate'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->postJson($route, ['type' => 'Identity'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $documents = route('configuration', ['tab' => 'documents']);
        $this->post($route, ['type' => 'Identity', 'name' => 'Birth certificate'])->assertRedirect($documents);
        $this->post($route, ['type' => 'Identity', 'name' => 'Birth certificate'])->assertRedirect($documents);
        $this->assertDatabaseCount('application_catalog_entries', 6);
        $this->get($documents)->assertOk()->assertSee('Birth certificate')->assertSee('Add new');
        $this->get(route('configuration', ['tab' => 'application-types']))
            ->assertOk()->assertSee('Identity — Birth certificate');
    }

    public function test_all_types_default_to_the_configurable_tabs_and_each_tab_can_be_opened(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (array_keys(ApplicationTypeSetting::types()) as $category) {
            $application = $this->application($user, $category);
            $response = $this->get(route('applications.show', $application))->assertOk()
                ->assertViewHas('applicationTabs', ApplicationTypeSetting::tabsFor($category))
                ->assertViewHas('activeApplicationTab', $category === 'Nomination' ? 'notes' : 'tasks')
                ->assertSee('Application Notes')->assertSee('Add Note');
            $this->assertSame(1, substr_count($response->getContent(), 'class="record-notes '));
            $this->get(route('applications.show', ['application' => $application, 'tab' => 'tasks']))
                ->assertOk()->assertSee('Application Notes')->assertSee('Add Note');
        }

        foreach (ApplicationTypeSetting::tabsFor('SBS') as $key => $label) {
            $response = $this->get(route('applications.show', ['application' => $application, 'tab' => $key]))
                ->assertOk()->assertViewHas('activeApplicationTab', $key)
                ->assertSee('aria-current="page"', false);
            if ($key === 'rfi') {
                $response->assertSee('No RFI records yet.')->assertSee('Add RFI');
            } elseif (isset(ApplicationActivity::TABS[$key])) {
                $response->assertSee('No '.strtolower($label).' records yet.')->assertSee('Save record');
            } elseif ($key === 'nominations') {
                $response->assertSee('No nominations reference this SBS.');
            } else {
                $response->assertSee($label.' content is work in progress.');
            }
        }
    }

    public function test_admin_configuration_changes_existing_and_new_applications_of_only_that_type(): void
    {
        $admin = User::factory()->create(['account_type' => 'Admin']);
        $existing = $this->application($admin);
        $visa = $this->application($admin, 'Visa');
        $this->actingAs($admin)->get(route('configuration', ['tab' => 'application-types']))
            ->assertOk()->assertSee('Choose the tabs shown')->assertSee('Primary Files');
        $this->patch(route('configuration.application-types.update'), [
            'category' => 'Study', 'tabs' => ['forms', 'rfi'],
        ])->assertRedirect(route('configuration', ['tab' => 'application-types']))->assertSessionHasNoErrors();

        $this->assertSame(['rfi', 'forms'], ApplicationTypeSetting::firstOrFail()->tabs);
        foreach ([$existing, $this->application($admin)] as $application) {
            $this->get(route('applications.show', $application))->assertOk()
                ->assertViewHas('applicationTabs', ['rfi' => 'RFI', 'forms' => 'Forms'])
                ->assertViewHas('activeApplicationTab', 'rfi')
                ->assertDontSee('id="application-tab-tasks"', false)
                ->assertDontSee('id="application-tab-payments"', false);
        }
        $this->get(route('applications.show', $visa))->assertOk()
            ->assertViewHas('applicationTabs', ApplicationTypeSetting::tabsFor('Visa'));
        $this->get(route('configuration', ['tab' => 'application-types']))->assertOk()
            ->assertViewHas('applicationTypeSettings', fn ($settings) => $settings['Study']->tabs === ['rfi', 'forms']);
    }

    public function test_disabled_unknown_and_malformed_tab_requests_fall_back_to_an_enabled_tab(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        ApplicationTypeSetting::create(['category' => 'Study', 'tabs' => ['quotes']]);

        foreach (['tasks', 'unknown', ['quotes']] as $tab) {
            $this->actingAs($user)->get(route('applications.show', ['application' => $application, 'tab' => $tab]))
                ->assertOk()->assertViewHas('activeApplicationTab', 'quotes')
                ->assertSee('Quotes content is work in progress.')
                ->assertDontSee('Tasks content is work in progress.');
        }
    }

    public function test_subtype_copies_defaults_and_can_override_them_including_tabs(): void
    {
        $admin = User::factory()->create(['account_type' => 'Admin']);
        $checkpoint = Checkpoint::create(['name' => 'Review', 'statuses' => ['Pending', 'Done']]);
        $passport = ApplicationCatalogEntry::create(['catalog' => 'required_documents', 'type' => 'Identity', 'name' => 'Passport']);
        $transcript = ApplicationCatalogEntry::create(['catalog' => 'required_documents', 'type' => 'Education', 'name' => 'Transcript']);
        $route = route('configuration.application-types.update');

        $this->actingAs($admin)->patch($route, [
            'category' => 'Study', 'mode' => 'type', 'service_agreement_id' => 7,
            'email_templates' => "Welcome\nFollow-up", 'required_documents' => [$passport->selectionValue()],
            'forms' => 'Intake', 'actions' => ['Request OSHC'], 'modules' => ['Program Options'],
            'checkpoint_ids' => [$checkpoint->id],
            'tabs' => ['forms'],
        ])->assertSessionHasNoErrors();

        $this->patch($route, ['category' => 'Study', 'mode' => 'create', 'name' => 'Higher Education'])
            ->assertSessionHasNoErrors();

        $type = ApplicationTypeSetting::firstOrFail();
        $workflow = ApplicationWorkflow::firstOrFail();
        $this->assertSame(collect($type->defaults)->except('migration_agent_id')->all() + ['tabs' => ['forms']], $workflow->defaults);
        $this->assertSame([$checkpoint->id], $workflow->checkpoint_ids);

        $this->patchJson($route, [
            'category' => 'Study', 'mode' => 'subtype', 'workflow_id' => $workflow->id, 'tabs' => ['tasks'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['tasks' => 'Tasks'], ApplicationTypeSetting::tabsFor('Study', workflow: $workflow->refresh()));

        $this->patch($route, [
            'category' => 'Study', 'mode' => 'subtype', 'workflow_id' => $workflow->id,
            'name' => 'University Study', 'service_agreement_id' => 6, 'required_documents' => [$transcript->selectionValue()],
        ])->assertSessionHasNoErrors();

        $workflow->refresh();
        $this->assertSame(6, $workflow->defaults['service_agreement_id']);
        $this->assertSame([$transcript->selectionValue()], $workflow->defaults['required_documents']);
        $this->assertSame([], $workflow->checkpoint_ids);
        $this->assertSame('University Study', $workflow->name);
        $this->get(route('configuration', ['tab' => 'application-types']))
            ->assertOk()->assertSee('Reset required documents to type default', false);
        $this->patch($route, [
            'category' => 'Study', 'mode' => 'reset', 'workflow_id' => $workflow->id,
            'section' => 'required_documents',
        ])->assertSessionHasNoErrors();
        $workflow->refresh();
        $this->assertSame([$passport->selectionValue()], $workflow->defaults['required_documents']);
        $this->assertSame(6, $workflow->defaults['service_agreement_id']);
        $this->assertSame([], $workflow->checkpoint_ids);
        $this->assertSame(7, $type->fresh()->defaults['service_agreement_id']);
        $this->assertSame(['forms' => 'Forms'], ApplicationTypeSetting::tabsFor('Study'));
    }

    public function test_all_tabs_can_be_disabled_and_reenabled(): void
    {
        $admin = User::factory()->create(['account_type' => 'Super Admin']);
        $application = $this->application($admin);
        $this->actingAs($admin)->patch(route('configuration.application-types.update'), ['category' => 'Study'])
            ->assertSessionHasNoErrors();
        $this->assertSame([], ApplicationTypeSetting::firstOrFail()->tabs);
        $this->get(route('applications.show', ['application' => $application, 'tab' => 'tasks']))
            ->assertOk()->assertViewHas('activeApplicationTab', null)
            ->assertSee('No tabs are enabled for this application type.')
            ->assertDontSee('aria-label="Application tabs"', false)->assertSee('Summary')->assertSee('Application Notes');
        $this->patch(route('configuration.application-types.update'), ['category' => 'Study', 'tabs' => ['primary-files']])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ApplicationTypeSetting::count());
        $this->get(route('applications.show', $application))->assertOk()
            ->assertViewHas('activeApplicationTab', 'primary-files');
    }

    public function test_invalid_configuration_is_rejected_without_overwriting_saved_tabs(): void
    {
        ApplicationTypeSetting::create(['category' => 'Study', 'tabs' => ['tasks']]);
        $this->actingAs(User::factory()->create(['account_type' => 'Admin']));
        foreach ([
            ['category' => 'Unknown', 'tabs' => ['tasks']],
            ['category' => 'Study', 'tabs' => ['unknown']],
            ['category' => 'Study', 'tabs' => ['tasks', 'tasks']],
            ['category' => 'Study', 'tabs' => 'tasks'],
            ['category' => 'Study', 'tabs' => [['tasks']]],
        ] as $payload) {
            $this->patchJson(route('configuration.application-types.update'), $payload)->assertUnprocessable();
            $this->assertSame(['tasks'], ApplicationTypeSetting::firstOrFail()->tabs);
            $this->assertSame(1, ApplicationTypeSetting::count());
        }
    }

    public function test_configuration_requires_admin_and_tabs_preserve_assignment_access(): void
    {
        $owner = User::factory()->create(['account_type' => 'Counsellor']);
        $application = $this->application($owner);
        $this->get(route('applications.show', $application))->assertRedirect(route('login'));
        $this->patch(route('configuration.application-types.update'), ['category' => 'Study'])
            ->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('configuration', ['tab' => 'application-types']))->assertForbidden();
        $this->patch(route('configuration.application-types.update'), ['category' => 'Study'])->assertForbidden();

        foreach (['Admin', 'Counsellor'] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]))
                ->get(route('applications.show', ['application' => $application, 'tab' => 'payments']))->assertNotFound();
        }
        $this->assertSame(0, ApplicationTypeSetting::count());
    }
}
