<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationTypeSetting;
use App\Models\ApplicationWorkflow;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbsRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 10)->startOfDay());
        $this->user = User::factory()->create();
        $this->organisation = Organisation::create(['name' => 'SBS Employer Pty Ltd',
            'trading_name' => 'SBS Trading', 'created_by_user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    private function sbs(array $attributes = []): Application
    {
        return Application::create($attributes + ['organisation_id' => $this->organisation->id,
            'category' => 'SBS', 'sponsor_type' => 'Standard Business Sponsor',
            'primary_counsellor_id' => $this->user->id, 'migration_agent_id' => $this->user->id,
        ]);
    }

    private function fields(array $attributes = []): array
    {
        return $attributes + ['status' => 'In Progress', 'priority' => 'M',
            'primary_counsellor_id' => $this->user->id, 'migration_agent_id' => $this->user->id];
    }

    public function test_record_has_editable_fields_readonly_identity_and_ordered_configurable_tabs(): void
    {
        $sbs = $this->sbs();
        $response = $this->get(route('applications.show', $sbs))->assertOk()
            ->assertSee($sbs->record_reference.' - SBS Application')->assertSee('SBS Trading')
            ->assertSee('Standard Business Sponsor')->assertSee('Sponsorship milestones')
            ->assertSee('Finalised - Refused')->assertSee('Expired')->assertSee('Appealing')
            ->assertSee('Application Notes')->assertSee('Save changes')
            ->assertDontSee('name="sponsor_type"', false)->assertDontSee('name="organisation_id"', false)
            ->assertViewHas('applicationTabs', ApplicationTypeSetting::availableTabs(ApplicationTypeSetting::SBS_TABS))
            ->assertSeeInOrder(['application-tab-tasks', 'application-tab-rfi', 'application-tab-nominations', 'application-tab-payments', 'application-tab-agreements']);
        foreach (['status', 'priority', 'critical_date', 'primary_counsellor_id', 'secondary_counsellor_id',
            'migration_agent_id', 'initial_consultation', 'service_agreement', 'supporting_documents',
            'application_stage', 'lodgement_date', 'trn', 'outcome', 'outcome_date', 'expiry_date',
            'appeal_deadline', 'appeal_status', 'art_case_number', 'final_outcome', 'final_outcome_date'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
        $this->get(route('applications.organisation-details.edit', $sbs))->assertOk()->assertSee('sbs-record-form');
        ApplicationTypeSetting::create(['category' => 'SBS', 'tabs' => ['nominations']]);
        $this->get(route('applications.show', ['application' => $sbs, 'tab' => 'rfi']))->assertOk()
            ->assertViewHas('activeApplicationTab', 'nominations');
        ApplicationTypeSetting::where('category', 'SBS')->update(['tabs' => []]);
        $this->get(route('applications.show', $sbs))->assertOk()->assertSee('No tabs are enabled');
    }

    public function test_creation_uses_workflow_agent_and_allows_reassignment_to_eligible_staff(): void
    {
        $agent = User::factory()->create();
        $workflow = ApplicationWorkflow::create(['name' => 'SBS workflow', 'category' => 'SBS', 'migration_agent_id' => $agent->id]);
        $this->post(route('organisations.applications.store', $this->organisation), [
            'category' => 'SBS', 'sponsor_type' => 'Standard Business Sponsor',
        ])->assertSessionHasNoErrors();
        $sbs = Application::sole();
        $this->assertSame($workflow->id, $sbs->application_workflow_id);
        $this->assertSame($agent->id, $sbs->migration_agent_id);
        $this->assertSame($this->user->id, $sbs->primary_counsellor_id);
        $this->assertSame('To do', $sbs->initial_consultation);
        $replacement = User::factory()->create();
        $this->patch(route('applications.update', $sbs), $this->fields(['migration_agent_id' => $replacement->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($replacement->id, $sbs->fresh()->migration_agent_id);
    }

    public function test_summary_milestones_dates_and_approval_persist_atomically(): void
    {
        $sbs = $this->sbs();
        $this->patch(route('applications.update', $sbs), $this->fields([
            'status' => 'Active', 'priority' => 'H', 'critical_date' => '2026-12-01',
            'initial_consultation' => 'Done', 'service_agreement' => 'Signed',
            'supporting_documents' => 'In review', 'application_stage' => 'In review',
            'lodgement_date' => '2026-10-01', 'trn' => 'ABC1234567890',
            'outcome' => 'Approved', 'outcome_date' => '2026-11-01', 'expiry_date' => '2031-11-01',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('applications.show', $sbs));
        $sbs->refresh();
        $this->assertSame('Active', $sbs->status);
        $this->assertSame('Signed', $sbs->service_agreement);
        $this->assertSame('Done', $sbs->initial_consultation);
        $this->assertSame('H', $sbs->priority);
        $this->assertSame('2026-11-01', $sbs->approval_date->format('Y-m-d'));
        $this->assertSame('2031-11-01', $sbs->expiry_date->format('Y-m-d'));
        $this->assertSame('2026-12-01', $sbs->critical_date->format('Y-m-d'));
        $this->patchJson(route('applications.update', $sbs), $this->fields(['status' => 'Finalised - Refused',
            'initial_consultation' => 'Invalid', 'priority' => 'L']))->assertUnprocessable();
        $this->assertSame('H', $sbs->fresh()->priority);
    }

    public function test_lodgement_sets_pending_decision_once_and_does_not_overwrite_later_outcomes(): void
    {
        $sbs = $this->sbs();
        $this->patch(route('applications.update', $sbs), $this->fields(['application_stage' => 'Lodged']))->assertSessionHasNoErrors();
        $this->assertSame('Pending Decision', $sbs->fresh()->status);
        $this->assertSame('Awaiting decision', $sbs->fresh()->outcome);
        $this->patch(route('applications.update', $sbs), $this->fields(['application_stage' => 'Lodged', 'status' => 'Active',
            'trn' => 'ABC1234567890', 'outcome' => 'Approved', 'outcome_date' => '2026-11-01', 'expiry_date' => '2031-11-01']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Active', $sbs->fresh()->status);
        $this->assertSame('Approved', $sbs->fresh()->outcome);
    }

    public function test_one_active_sbs_is_enforced_across_staff_but_other_organisations_and_labour_are_independent(): void
    {
        $hidden = User::factory()->create();
        $existing = $this->sbs(['status' => 'Active', 'primary_counsellor_id' => $hidden->id, 'migration_agent_id' => $hidden->id]);
        $next = $this->sbs();
        $active = $this->fields(['status' => 'Active', 'trn' => 'ABC1234567890']);
        $this->patchJson(route('applications.update', $next), $active)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('Not Started', $next->fresh()->status);
        $existing->update(['expiry_date' => today()->toDateString()]);
        $this->sbs(['sponsor_type' => 'Labour Agreement', 'status' => 'Active']);
        $other = Organisation::create(['name' => 'Other Employer', 'created_by_user_id' => $this->user->id]);
        $this->sbs(['organisation_id' => $other->id, 'status' => 'Active']);
        $this->patch(route('applications.update', $next), $active)->assertSessionHasNoErrors();
        $this->assertSame('Expired', $existing->fresh()->status);
        $this->patch(route('applications.update', $next), $active)->assertSessionHasNoErrors();
    }

    public function test_active_trn_staff_and_readonly_fields_are_validated(): void
    {
        $sbs = $this->sbs();
        foreach ([null, '', 'ABC123', 'ABC12345678901', 'ABC12345678-0', ['ABC1234567890']] as $trn) {
            $this->patchJson(route('applications.update', $sbs), $this->fields(['status' => 'Active', 'trn' => $trn]))
                ->assertUnprocessable()->assertJsonValidationErrors('trn');
        }
        foreach (['migration_agent_id', 'primary_counsellor_id'] as $field) {
            $this->patchJson(route('applications.update', $sbs), $this->fields([$field => null]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $inactive = User::factory()->create(['is_active' => false]);
        $this->patchJson(route('applications.update', $sbs), $this->fields(['migration_agent_id' => $inactive->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('migration_agent_id');
        foreach (['sponsor_type' => 'Labour Agreement', 'organisation_id' => 999, 'category' => 'Nomination', 'id' => 999] as $field => $value) {
            $this->patchJson(route('applications.update', $sbs), $this->fields([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->patchJson(route('applications.organisation-details.update', $sbs), ['trn' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors('trn');
        $this->assertSame('Not Started', $sbs->fresh()->status);
    }

    public function test_appeal_requirements_and_original_outcome_are_preserved(): void
    {
        $sbs = $this->sbs();
        $refused = $this->fields(['status' => 'Appealing', 'outcome' => 'Refused', 'outcome_date' => '2026-10-01',
            'appeal_status' => 'Set aside - substituted', 'final_outcome' => 'Approved']);
        $this->patchJson(route('applications.update', $sbs), $refused)->assertUnprocessable()
            ->assertJsonValidationErrors(['final_outcome_date', 'expiry_date']);
        $this->patchJson(route('applications.update', $sbs), $this->fields(['outcome' => 'Approved']))
            ->assertUnprocessable()->assertJsonValidationErrors(['outcome_date', 'expiry_date']);
        $this->patchJson(route('applications.update', $sbs), $this->fields(['outcome' => 'Refused', 'outcome_date' => '2026-10-01',
            'appeal_status' => 'Not reviewable', 'appeal_deadline' => '2026-10-20']))
            ->assertUnprocessable()->assertJsonValidationErrors('appeal_deadline');
        $this->patch(route('applications.update', $sbs), $refused + ['final_outcome_date' => '2026-11-01',
            'expiry_date' => '2031-11-01', 'art_case_number' => 'ART-123', 'appeal_deadline' => '2026-10-20'])
            ->assertSessionHasNoErrors();
        $sbs->refresh();
        $this->assertSame('Refused', $sbs->outcome);
        $this->assertSame('Approved', $sbs->final_outcome);
        $this->assertSame('2026-11-01', $sbs->approval_date->format('Y-m-d'));
        $this->patch(route('applications.update', $sbs), $this->fields(['outcome' => 'Awaiting decision']))->assertSessionHasNoErrors();
        $this->assertNull($sbs->fresh()->expiry_date);
        $this->assertNull($sbs->fresh()->final_outcome);
    }

    public function test_scheduler_and_requests_expire_due_records_inclusively_and_idempotently(): void
    {
        $past = $this->sbs(['status' => 'Active', 'expiry_date' => '2026-11-09']);
        $due = $this->sbs(['status' => 'Active', 'expiry_date' => '2026-11-10']);
        $future = $this->sbs(['status' => 'Active', 'expiry_date' => '2026-11-11']);
        $labour = $this->sbs(['status' => 'Active', 'sponsor_type' => 'Labour Agreement', 'expiry_date' => '2026-11-09']);
        $this->artisan('sbs:expire')->expectsOutput('Expired 2 SBS record(s).')->assertSuccessful();
        $this->artisan('sbs:expire')->expectsOutput('Expired 0 SBS record(s).')->assertSuccessful();
        $this->assertSame('Expired', $past->fresh()->status);
        $this->assertSame('Expired', $due->fresh()->status);
        $this->assertSame('Active', $future->fresh()->status);
        $this->assertSame('Active', $labour->fresh()->status);
        $this->travelTo(now()->addDay());
        $this->get(route('applications.show', $future))->assertOk();
        $this->assertSame('Expired', $future->fresh()->status);
    }

    public function test_saving_a_reached_expiry_keeps_the_record_expired(): void
    {
        $sbs = $this->sbs();
        $this->patch(route('applications.update', $sbs), $this->fields(['status' => 'Active', 'trn' => 'ABC1234567890',
            'outcome' => 'Approved', 'outcome_date' => '2026-11-01', 'expiry_date' => '2026-11-10']))->assertSessionHasNoErrors();
        $this->assertSame('Expired', $sbs->fresh()->status);
    }

    public function test_nominations_are_read_only_and_scoped_to_sbs_organisation_and_viewer(): void
    {
        $sbs = $this->sbs();
        $otherSbs = $this->sbs();
        $base = ['category' => 'Nomination', 'nomination_pathway' => '482 - Skills in Demand (SID) Visa',
            'primary_counsellor_id' => $this->user->id, 'sponsorship_id' => $sbs->id];
        $visible = $this->organisation->applications()->create($base + ['nominee_name' => 'Visible Nominee']);
        $hidden = User::factory()->create();
        $this->organisation->applications()->create(array_replace($base, ['nominee_name' => 'Hidden Nominee', 'primary_counsellor_id' => $hidden->id]));
        $this->organisation->applications()->create(array_replace($base, ['nominee_name' => 'Other SBS Nominee', 'sponsorship_id' => $otherSbs->id]));
        $client = Client::create(['client_id' => 7701, 'first_name' => 'Ana', 'middle_name' => 'Maria', 'surname' => 'Santos',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '', 'admin_office' => 'Melbourne',
            'primary_counsellor' => $this->user->name]);
        $this->organisation->applications()->create($base + ['nominee_client_id' => $client->id]);
        $this->get(route('applications.show', ['application' => $sbs, 'tab' => 'nominations']))->assertOk()
            ->assertSee('Visible Nominee')->assertSee('Ana Maria Santos')->assertSee($visible->record_reference)
            ->assertSee('482 - Skills in Demand (SID) Visa')->assertDontSee('Hidden Nominee')->assertDontSee('Other SBS Nominee')
            ->assertDontSee('Link/Unlink')->assertDontSee('Create nomination')->assertDontSee('activity-form');
        $this->actingAs($hidden)->get(route('applications.show', $sbs))->assertNotFound();
        $this->patchJson(route('applications.update', $sbs), $this->fields())->assertNotFound();
    }

    public function test_agreement_events_update_the_sponsorship_milestone(): void
    {
        $sbs = $this->sbs();
        $agreement = $sbs->activities()->create(['kind' => 'agreements', 'title' => 'SBS service agreement', 'status' => 'Sent']);
        $this->assertSame('Sent - Pending Client', $sbs->fresh()->service_agreement);
        $agreement->update(['status' => 'Digitally signed']);
        $this->assertSame('Signed', $sbs->fresh()->service_agreement);
        $sbs->activities()->create(['kind' => 'tasks', 'title' => 'Other task', 'status' => 'Sent']);
        $this->assertSame('Signed', $sbs->fresh()->service_agreement);
    }

    public function test_labour_type_cannot_be_changed_to_bypass_the_active_sbs_rule(): void
    {
        $this->sbs(['status' => 'Active']);
        $labour = $this->sbs(['sponsor_type' => 'Labour Agreement', 'status' => 'Active']);
        $this->patchJson(route('applications.organisation-details.update', $labour), [
            'sponsor_type' => 'Standard Business Sponsor',
        ])->assertUnprocessable()->assertJsonValidationErrors('sponsor_type');
        $this->assertSame('Labour Agreement', $labour->fresh()->sponsor_type);
    }

    public function test_migration_preserves_legacy_sbs_approval_without_changing_labour_outcomes(): void
    {
        $sbs = $this->sbs(['approval_date' => '2026-01-01', 'expiry_date' => '2031-01-01']);
        $labour = $this->sbs(['sponsor_type' => 'Labour Agreement', 'approval_date' => '2026-01-01']);
        $migration = require database_path('migrations/2026_11_10_000001_add_sbs_milestones.php');
        $migration->down();
        $migration->up();
        $this->assertSame('Approved', $sbs->fresh()->outcome);
        $this->assertSame('2026-01-01', $sbs->fresh()->outcome_date->format('Y-m-d'));
        $this->assertSame('2031-01-01', $sbs->fresh()->expiry_date->format('Y-m-d'));
        $this->assertNull($labour->fresh()->outcome);
    }
}
