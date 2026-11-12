<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationTypeSetting;
use App\Models\ApplicationWorkflow;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabourAgreementRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 12)->startOfDay());
        $this->user = User::factory()->create();
        $this->organisation = Organisation::create(['name' => 'Labour Employer Pty Ltd',
            'trading_name' => 'Labour Trading', 'created_by_user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    private function labour(array $attributes = []): Application
    {
        return $this->organisation->applications()->create($attributes + ['category' => 'SBS',
            'sponsor_type' => 'Labour Agreement', 'primary_counsellor_id' => $this->user->id,
            'migration_agent_id' => $this->user->id]);
    }

    private function fields(array $attributes = []): array
    {
        return $attributes + ['status' => 'In Progress', 'priority' => 'M',
            'primary_counsellor_id' => $this->user->id, 'migration_agent_id' => $this->user->id];
    }

    public function test_record_has_lab_identity_full_editable_workflow_and_no_trn(): void
    {
        $labour = $this->labour();
        $response = $this->get(route('applications.show', $labour))->assertOk()
            ->assertSee($labour->record_reference.' - Labour Agreement Application')
            ->assertSee('LAB ID')->assertSee('Labour Trading')->assertSee('Labour Agreement Details')
            ->assertDontSee('Standard Business Sponsor')->assertDontSee('SBS Application')->assertDontSee('SBS ID')
            ->assertDontSee('name="trn"', false)->assertDontSee('TRN')
            ->assertDontSee('name="sponsor_type"', false)->assertDontSee('name="organisation_id"', false)
            ->assertSee('Application Notes')->assertSee('Appealing')->assertSee('Expired')
            ->assertSeeInOrder(['application-tab-tasks', 'application-tab-rfi', 'application-tab-nominations', 'application-tab-payments', 'application-tab-agreements']);
        foreach (['status', 'priority', 'critical_date', 'primary_counsellor_id', 'secondary_counsellor_id',
            'migration_agent_id', 'initial_consultation', 'service_agreement', 'supporting_documents',
            'application_stage', 'lodgement_date', 'outcome', 'outcome_date', 'expiry_date',
            'appeal_deadline', 'appeal_status', 'art_case_number', 'final_outcome', 'final_outcome_date'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
        $this->get(route('applications.organisation-details.edit', $labour))->assertOk()->assertSee('sbs-record-form');
        $this->get(route('applications', ['description' => 'Labour Agreement']))->assertOk()
            ->assertSee('Labour Employer Pty Ltd')->assertDontSee('Standard Business Sponsorship');
        ApplicationTypeSetting::create(['category' => 'SBS', 'tabs' => ['nominations']]);
        $this->get(route('applications.show', $labour))->assertOk()->assertViewHas('activeApplicationTab', 'nominations');
        ApplicationTypeSetting::where('category', 'SBS')->update(['tabs' => []]);
        $this->get(route('applications.show', $labour))->assertOk()->assertSee('No tabs are enabled');
    }

    public function test_creation_defaults_workflow_agent_and_rejects_trn(): void
    {
        $agent = User::factory()->create();
        $workflow = ApplicationWorkflow::create(['name' => 'Sponsorship workflow', 'category' => 'SBS', 'migration_agent_id' => $agent->id]);
        $payload = ['category' => 'SBS', 'sponsor_type' => 'Labour Agreement'];
        $this->postJson(route('organisations.applications.store', $this->organisation), $payload + ['trn' => 'ABC1234567890'])
            ->assertUnprocessable()->assertJsonValidationErrors('trn');
        $this->post(route('organisations.applications.store', $this->organisation), $payload)->assertSessionHasNoErrors();
        $labour = Application::sole();
        $this->assertSame($workflow->id, $labour->application_workflow_id);
        $this->assertSame($agent->id, $labour->migration_agent_id);
        $this->assertSame($this->user->id, $labour->primary_counsellor_id);
        $this->assertSame('To do', $labour->initial_consultation);
        $this->assertStringStartsWith('LAB-', $labour->record_reference);
    }

    public function test_summary_milestones_lodgement_and_approval_save_without_trn(): void
    {
        $labour = $this->labour();
        $this->patch(route('applications.update', $labour), $this->fields(['application_stage' => 'Lodged']))->assertSessionHasNoErrors();
        $this->assertSame('Pending Decision', $labour->fresh()->status);
        $this->assertSame('Awaiting decision', $labour->fresh()->outcome);
        $replacement = User::factory()->create();
        $this->patch(route('applications.update', $labour), $this->fields(['status' => 'Active', 'priority' => 'H',
            'critical_date' => '2026-12-01', 'initial_consultation' => 'Done', 'service_agreement' => 'Signed',
            'supporting_documents' => 'Done', 'application_stage' => 'Lodged', 'lodgement_date' => '2026-11-01',
            'outcome' => 'Approved', 'outcome_date' => '2026-11-02', 'expiry_date' => '2031-11-02',
            'migration_agent_id' => $replacement->id]))->assertSessionHasNoErrors();
        $labour->refresh();
        $this->assertSame('Active', $labour->status);
        $this->assertSame('H', $labour->priority);
        $this->assertSame('Done', $labour->supporting_documents);
        $this->assertSame('Signed', $labour->service_agreement);
        $this->assertSame('2026-11-02', $labour->approval_date->format('Y-m-d'));
        $this->assertSame($replacement->id, $labour->migration_agent_id);
        $this->assertNull($labour->trn);
        $this->postJson('/applications/'.$labour->id.'/notes', ['version' => hash('sha256', ''),
            'body' => 'Labour Agreement progress note'])->assertOk();
        $this->assertStringContainsString('Labour Agreement progress note', $labour->fresh()->notes);
        $this->patchJson(route('applications.update', $labour), $this->fields(['initial_consultation' => 'Invalid', 'priority' => 'L']))
            ->assertUnprocessable()->assertJsonValidationErrors('initial_consultation');
        $this->assertSame('H', $labour->fresh()->priority);
    }

    public function test_refusal_appeal_and_expiry_dates_are_validated_and_persisted(): void
    {
        $labour = $this->labour();
        $fields = $this->fields(['status' => 'Appealing', 'outcome' => 'Refused', 'outcome_date' => '2026-11-01',
            'appeal_status' => 'Set aside - substituted', 'final_outcome' => 'Approved']);
        $this->patchJson(route('applications.update', $labour), $fields)->assertUnprocessable()
            ->assertJsonValidationErrors(['final_outcome_date', 'expiry_date']);
        $this->patchJson(route('applications.update', $labour), $fields + ['final_outcome_date' => '2026-11-10', 'expiry_date' => '2026-11-09'])
            ->assertUnprocessable()->assertJsonValidationErrors('expiry_date');
        $this->patchJson(route('applications.update', $labour), $this->fields(['outcome' => 'Refused', 'outcome_date' => '2026-11-01',
            'appeal_status' => 'Not reviewable', 'appeal_deadline' => '2026-11-20']))->assertUnprocessable()->assertJsonValidationErrors('appeal_deadline');
        $this->patch(route('applications.update', $labour), $fields + ['final_outcome_date' => '2026-11-10',
            'expiry_date' => '2031-11-10', 'art_case_number' => 'ART-LAB-1'])->assertSessionHasNoErrors();
        $this->assertSame('Refused', $labour->fresh()->outcome);
        $this->assertSame('Approved', $labour->fresh()->final_outcome);
        $this->assertSame('2026-11-10', $labour->fresh()->approval_date->format('Y-m-d'));
    }

    public function test_only_one_labour_agreement_is_active_across_staff_and_sbs_is_independent(): void
    {
        $hidden = User::factory()->create();
        $existing = $this->labour(['status' => 'Active', 'primary_counsellor_id' => $hidden->id, 'migration_agent_id' => $hidden->id]);
        $next = $this->labour();
        $this->patchJson(route('applications.update', $next), $this->fields(['status' => 'Active']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $existing->update(['expiry_date' => today()->toDateString()]);
        $this->labour(['status' => 'Active', 'sponsor_type' => 'Standard Business Sponsor']);
        $this->patch(route('applications.update', $next), $this->fields(['status' => 'Active']))->assertSessionHasNoErrors();
        $this->assertSame('Active', $next->fresh()->status);
        $this->assertSame('Expired', $existing->fresh()->status);
        $this->artisan('sbs:expire')->assertSuccessful();
        $this->assertSame('Active', $next->fresh()->status);
        $next->update(['expiry_date' => today()->toDateString()]);
        $this->get(route('applications.show', $next))->assertOk();
        $this->assertSame('Expired', $next->fresh()->status);
    }

    public function test_trn_identity_staff_and_record_access_are_enforced_on_both_update_routes(): void
    {
        $labour = $this->labour();
        foreach (['applications.update', 'applications.organisation-details.update'] as $route) {
            foreach (['trn' => 'ABC1234567890', 'sponsor_type' => 'Standard Business Sponsor'] as $field => $value) {
                $this->patchJson(route($route, $labour), $this->fields([$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        foreach (['migration_agent_id', 'primary_counsellor_id'] as $field) {
            $this->patchJson(route('applications.update', $labour), $this->fields([$field => null]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $inactive = User::factory()->create(['is_active' => false]);
        $this->patchJson(route('applications.update', $labour), $this->fields(['migration_agent_id' => $inactive->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('migration_agent_id');
        $this->actingAs(User::factory()->create());
        $this->get(route('applications.show', $labour))->assertNotFound();
        $this->patchJson(route('applications.update', $labour), $this->fields())->assertNotFound();
    }

    public function test_nominations_are_read_only_and_scoped_and_agreement_events_update_milestones(): void
    {
        $labour = $this->labour();
        $base = ['category' => 'Nomination', 'primary_counsellor_id' => $this->user->id,
            'nomination_pathway' => '482 - Skills in Demand (SID) Visa', 'sponsorship_id' => $labour->id];
        $visible = $this->organisation->applications()->create($base + ['nominee_name' => 'Visible Labour Nominee']);
        $this->organisation->applications()->create(array_replace($base, ['nominee_name' => 'Other Sponsorship', 'sponsorship_id' => $this->labour()->id]));
        $this->organisation->applications()->create(array_replace($base, ['nominee_name' => 'Hidden Nominee', 'primary_counsellor_id' => User::factory()->create()->id]));
        $this->get(route('applications.show', ['application' => $labour, 'tab' => 'nominations']))->assertOk()
            ->assertSee('Nominations that reference this Labour Agreement')->assertSee('Visible Labour Nominee')
            ->assertSee($visible->record_reference)->assertDontSee('Other Sponsorship')->assertDontSee('Hidden Nominee')
            ->assertDontSee('Create nomination')->assertDontSee('Link/Unlink');
        $agreement = $labour->activities()->create(['kind' => 'agreements', 'title' => 'Labour service agreement', 'status' => 'Sent']);
        $this->assertSame('Sent - Pending Client', $labour->fresh()->service_agreement);
        $agreement->update(['status' => 'Digitally signed']);
        $this->assertSame('Signed', $labour->fresh()->service_agreement);
    }

    public function test_migration_preserves_legacy_approvals_and_existing_decisions(): void
    {
        $legacy = $this->labour(['approval_date' => '2026-01-01', 'expiry_date' => '2031-01-01']);
        $decided = $this->labour(['approval_date' => '2026-01-01', 'outcome' => 'Refused', 'outcome_date' => '2026-02-01']);
        $migration = require database_path('migrations/2026_11_12_000001_backfill_labour_agreement_outcomes.php');
        $migration->up();
        $migration->up();
        $this->assertSame('Approved', $legacy->fresh()->outcome);
        $this->assertSame('2026-01-01', $legacy->fresh()->outcome_date->format('Y-m-d'));
        $this->assertSame('2031-01-01', $legacy->fresh()->expiry_date->format('Y-m-d'));
        $this->assertSame('Refused', $decided->fresh()->outcome);
        $this->assertSame('2026-02-01', $decided->fresh()->outcome_date->format('Y-m-d'));
    }
}
