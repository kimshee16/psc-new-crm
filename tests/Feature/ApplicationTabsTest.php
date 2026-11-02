<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationTypeSetting;
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

    public function test_all_types_default_to_the_seven_tabs_and_each_tab_can_be_opened(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (array_keys(ApplicationTypeSetting::types()) as $category) {
            $application = $this->application($user, $category);
            $this->get(route('applications.show', $application))->assertOk()
                ->assertViewHas('applicationTabs', ApplicationTypeSetting::TABS)
                ->assertViewHas('activeApplicationTab', 'tasks');
        }

        foreach (ApplicationTypeSetting::TABS as $key => $label) {
            $this->get(route('applications.show', ['application' => $application, 'tab' => $key]))
                ->assertOk()->assertViewHas('activeApplicationTab', $key)
                ->assertSee($label.' content is work in progress.')
                ->assertSee('aria-current="page"', false);
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
            ->assertViewHas('applicationTabs', ApplicationTypeSetting::TABS);
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
