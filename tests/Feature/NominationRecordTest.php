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

class NominationRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organisation = Organisation::create(['name' => 'Nomination Employer Pty Ltd',
            'trading_name' => 'Nomination Trading', 'created_by_user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    private function nomination(array $attributes = []): Application
    {
        return $this->organisation->applications()->create($attributes + [
            'category' => 'Nomination', 'primary_counsellor_id' => $this->user->id,
            'nominee_name' => 'External Nominee', 'nomination_pathway' => '482 - Skills in Demand (SID) Visa',
        ]);
    }

    private function visa(array $attributes = []): Application
    {
        $client = Client::create(['client_id' => 7500 + Client::count(), 'first_name' => 'Ana', 'surname' => 'Santos',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => $this->user->name]);

        return Application::create($attributes + ['client_id' => $client->id, 'category' => 'Visa',
            'visa_subclass' => '186 - Employer Nomination Scheme Visa', 'primary_counsellor_id' => $this->user->id]);
    }

    private function fields(array $changes = []): array
    {
        return $changes + ['status' => 'In Progress', 'priority' => 'H',
            'primary_counsellor_id' => $this->user->id, 'critical_date' => '2026-12-01'];
    }

    private function link(Application $a, Application $b, string $action = 'link')
    {
        return $this->postJson(route('applications.links.update', $a), ['linked_application_id' => $b->id, 'action' => $action]);
    }

    public function test_record_is_editable_with_organisation_identity_default_tabs_and_all_nomination_fields(): void
    {
        $nomination = $this->nomination();
        $response = $this->get(route('applications.show', $nomination))->assertOk()
            ->assertSee($nomination->record_reference.' - Nomination Application')->assertSee('Nomination Trading')
            ->assertSee('href="'.route('organisations.show', $this->organisation).'"', false)
            ->assertSee('Actions')->assertSee('Google Drive')->assertSee('Save changes')
            ->assertSee('External Nominee')->assertSee('Appealing')->assertSee('Application Notes')
            ->assertSee('This subclass requires SBS')->assertViewHas('hasActiveSbs', false)
            ->assertViewHas('applicationTabs', ApplicationTypeSetting::availableTabs(ApplicationTypeSetting::NOMINATION_TABS));
        foreach (['nominated_position_title', 'anzsco_code', 'annual_salary', 'lodgement_date', 'reference_number',
            'outcome', 'outcome_date', 'expiry_date', 'appeal_deadline', 'appeal_status', 'art_case_number', 'final_outcome', 'final_outcome_date'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
    }

    public function test_save_persists_summary_nomination_and_refused_appeal_without_changing_original_outcome(): void
    {
        $agent = User::factory()->create();
        $workflow = ApplicationWorkflow::create(['name' => 'Nomination workflow', 'category' => 'Nomination', 'migration_agent_id' => $agent->id]);
        $nomination = $this->nomination(['application_workflow_id' => $workflow->id]);
        $this->assertSame($agent->id, $nomination->migration_agent_id);
        $this->patch(route('applications.update', $nomination), $this->fields([
            'status' => 'Appealing', 'nominee_name' => 'Updated Nominee', 'external_nominee' => '1',
            'nomination_pathway' => '494 - Skilled Employer Sponsored Regional Visa',
            'nominated_position_title' => 'Software Engineer', 'anzsco_code' => '261313', 'annual_salary' => '123456.78',
            'lodgement_date' => '2026-10-01', 'reference_number' => 'REF-75', 'outcome' => 'Refused',
            'outcome_date' => '2026-10-20', 'appeal_deadline' => '2026-11-20', 'appeal_status' => 'Set aside - remitted',
            'art_case_number' => 'ART-75', 'final_outcome' => 'Approved', 'final_outcome_date' => '2026-11-01',
            'expiry_date' => '2030-11-01', 'google_drive_url' => 'https://drive.google.com/drive/folders/example',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('applications.show', $nomination));
        $this->assertDatabaseHas('applications', ['id' => $nomination->id, 'outcome' => 'Refused',
            'status' => 'Appealing', 'final_outcome' => 'Approved', 'annual_salary' => '123456.78', 'anzsco_code' => '261313',
            'nominee_name' => 'Updated Nominee', 'art_case_number' => 'ART-75']);
        $this->assertSame('2030-11-01', $nomination->fresh()->expiry_date->format('Y-m-d'));
        $this->get(route('applications.show', $nomination))->assertOk()->assertSee('REF-75')->assertSee('ART-75')
            ->assertSee('https://drive.google.com/drive/folders/example');
    }

    public function test_conditional_dates_codes_salary_and_staff_are_validated_atomically(): void
    {
        $nomination = $this->nomination();
        foreach ([
            [['outcome' => 'Approved'], 'outcome_date'],
            [['outcome' => 'Refused'], 'outcome_date'],
            [['outcome' => 'Withdrawn'], 'outcome_date'],
            [['outcome' => 'Refused', 'outcome_date' => '2026-11-01', 'final_outcome' => 'Refused'], 'final_outcome_date'],
            [['outcome' => 'Refused', 'outcome_date' => '2026-11-01', 'final_outcome' => 'Approved', 'final_outcome_date' => '2026-11-02'], 'expiry_date'],
            [['outcome' => 'Refused', 'outcome_date' => '2026-11-01', 'appeal_status' => 'Not reviewable', 'appeal_deadline' => '2026-12-01'], 'appeal_deadline'],
            [['anzsco_code' => '12345'], 'anzsco_code'], [['anzsco_code' => 'ABC123'], 'anzsco_code'],
            [['annual_salary' => '-1'], 'annual_salary'], [['annual_salary' => '123.456'], 'annual_salary'],
            [['lodgement_date' => '2026-02-30'], 'lodgement_date'], [['outcome' => 'Invalid'], 'outcome'],
            [['secondary_counsellor_id' => User::factory()->create(['is_active' => false])->id], 'secondary_counsellor_id'],
            [['google_drive_url' => 'javascript:alert(1)'], 'google_drive_url'],
        ] as [$changes, $error]) {
            $this->patchJson(route('applications.update', $nomination), $this->fields($changes))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
            $this->assertSame('Not Started', $nomination->fresh()->status);
            $this->assertNull($nomination->fresh()->outcome);
        }
    }

    public function test_creation_also_enforces_outcome_dates_and_rolls_back_invalid_records(): void
    {
        $payload = ['category' => 'Nomination', 'external_nominee' => '1', 'nominee_name' => 'Creation Nominee',
            'visa_subclass' => '482 - Skills in Demand (SID) Visa', 'outcome' => 'Approved'];
        $this->postJson(route('organisations.applications.store', $this->organisation), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('outcome_date');
        $this->assertDatabaseCount('applications', 0);
        $this->post(route('organisations.applications.store', $this->organisation), $payload + ['outcome_date' => '2026-11-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-01', Application::sole()->outcome_date->format('Y-m-d'));
    }

    public function test_nominee_can_switch_between_public_client_id_and_external_name_and_subclass_is_configured(): void
    {
        $nomination = $this->nomination();
        $client = $this->visa()->client;
        $this->patch(route('applications.update', $nomination), $this->fields(['external_nominee' => '0',
            'nominee_client_reference' => $client->client_id, 'nomination_pathway' => '186 - Employer Nomination Scheme Visa']))->assertSessionHasNoErrors();
        $this->assertSame($client->id, $nomination->fresh()->nominee_client_id);
        $this->assertNull($nomination->fresh()->nominee_name);
        $this->patch(route('applications.update', $nomination), $this->fields(['external_nominee' => '1',
            'nominee_client_reference' => $client->client_id, 'nominee_name' => 'External Replacement']))->assertSessionHasNoErrors();
        $this->assertNull($nomination->fresh()->nominee_client_id);
        $this->assertSame('External Replacement', $nomination->fresh()->nominee_name);
        $this->patchJson(route('applications.update', $nomination), $this->fields(['nomination_pathway' => '500 - Student Visa']))
            ->assertUnprocessable()->assertJsonValidationErrors('nomination_pathway');
        $this->patchJson(route('applications.update', $nomination), $this->fields(['external_nominee' => '1', 'nominee_name' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('nominee_name');
    }

    public function test_linking_from_either_side_populates_locks_and_unlinking_resets_identity(): void
    {
        $nomination = $this->nomination();
        $visa = $this->visa();
        $this->link($visa, $nomination)->assertRedirect();
        $this->assertSame($visa->client_id, $nomination->fresh()->nominee_client_id);
        $this->assertSame($visa->visa_subclass, $nomination->fresh()->nomination_pathway);
        $this->assertNull($nomination->fresh()->nominee_name);
        $this->get(route('applications.show', $nomination))->assertOk()->assertViewHas('nomineeLocked', true)
            ->assertSee('Ana Santos')->assertDontSee('name="nomination_pathway"', false)->assertDontSee('name="nominee_client_reference"', false);
        foreach (['applications.update', 'applications.organisation-details.update'] as $route) {
            $this->patchJson(route($route, $nomination), $this->fields(['nominee_name' => 'Tampered']))
                ->assertUnprocessable()->assertJsonValidationErrors('nominee_name');
        }
        $this->patch(route('applications.update', $nomination), $this->fields(['nominated_position_title' => 'Chef']))->assertSessionHasNoErrors();
        $visa->update(['visa_subclass' => '494 - Skilled Employer Sponsored Regional Visa']);
        $this->assertSame($visa->visa_subclass, $nomination->fresh()->nomination_pathway);
        $this->link($nomination, $visa, 'unlink')->assertRedirect();
        foreach (['nominee_client_id', 'nominee_name', 'nomination_pathway'] as $field) {
            $this->assertNull($nomination->fresh()->$field);
        }
        $this->get(route('applications.show', $nomination))->assertOk()->assertViewHas('nomineeLocked', false);
        $this->patchJson(route('applications.update', $nomination), $this->fields())->assertUnprocessable()
            ->assertJsonValidationErrors(['nominee_client_reference', 'nomination_pathway']);
    }

    public function test_one_to_one_rule_handles_reciprocal_legacy_and_hidden_links(): void
    {
        $a = $this->nomination();
        $b = $this->nomination();
        $visa = $this->visa();
        $otherVisa = $this->visa();
        $visa->linked_application_id = $a->id;
        $visa->save();
        $this->link($b, $visa)->assertUnprocessable()->assertJsonValidationErrors('linked_application_id');
        $this->link($otherVisa, $a)->assertUnprocessable();
        $this->link($a, $visa)->assertRedirect(); // idempotent legacy pair
        $visa->update(['primary_counsellor_id' => User::factory()->create()->id]);
        $this->get(route('applications.show', $a))->assertOk()->assertViewHas('nomineeLocked', true)
            ->assertViewHas('linkedItems', fn ($items) => $items->isEmpty());
        $this->link($a, $otherVisa)->assertUnprocessable();
        $this->link($a, $visa, 'unlink')->assertNotFound();
        $this->patchJson(route('applications.update', $b), $this->fields(['nominee_client_reference' => 999999, 'external_nominee' => '0']))->assertUnprocessable();
    }

    public function test_batch_replace_is_atomic_and_rejects_multiple_visas(): void
    {
        $nomination = $this->nomination();
        $old = $this->visa();
        $new = $this->visa();
        $third = $this->visa();
        $this->link($nomination, $old);
        $this->postJson(route('applications.links.update', $nomination), ['action' => 'save',
            'add' => [$new->id, $third->id], 'remove' => [$old->id]])->assertUnprocessable();
        $this->assertSame([$old->id], Application::query()->linkedTo($nomination)->pluck('id')->all());
        $this->assertSame($old->client_id, $nomination->fresh()->nominee_client_id);
        $this->postJson(route('applications.links.update', $nomination), ['action' => 'save',
            'add' => [$new->id], 'remove' => [$old->id]])->assertOk();
        $this->assertSame([$new->id], Application::query()->linkedTo($nomination)->pluck('id')->all());
        $this->assertSame($new->client_id, $nomination->fresh()->nominee_client_id);
    }

    public function test_suggestions_use_active_visas_under_nominee_and_exact_id_supports_another_client(): void
    {
        $visa = $this->visa();
        $nomination = $this->nomination(['nominee_name' => null, 'nominee_client_id' => $visa->client_id]);
        $other = $this->visa();
        $this->visa(['client_id' => $visa->client_id, 'status' => 'Finalised']);
        $this->getJson(route('applications.links.edit', $nomination))->assertOk()
            ->assertJsonCount(1, 'candidates.data')->assertJsonPath('candidates.data.0.id', $visa->id);
        $this->getJson(route('applications.links.edit', ['application' => $nomination, 'application_id' => $other->id]))
            ->assertOk()->assertJsonPath('candidates.data.0.id', $other->id);
    }

    public function test_sponsorship_default_uses_requirement_and_active_same_organisation_sbs(): void
    {
        $expired = $this->organisation->applications()->create(['category' => 'SBS', 'status' => 'Active',
            'sponsor_type' => 'Standard Business Sponsor', 'expiry_date' => today()->subDay(), 'primary_counsellor_id' => $this->user->id]);
        $active = $this->organisation->applications()->create(['category' => 'SBS', 'status' => 'Active',
            'sponsor_type' => 'Standard Business Sponsor', 'expiry_date' => today()->addYear(), 'primary_counsellor_id' => $this->user->id]);
        $labour = $this->organisation->applications()->create(['category' => 'SBS', 'status' => 'Active',
            'sponsor_type' => 'Labour Agreement', 'primary_counsellor_id' => $this->user->id]);
        foreach (['482 - Skills in Demand (SID) Visa' => $active->id, '186 - Employer Nomination Scheme Visa' => null] as $subclass => $expected) {
            $this->post(route('organisations.applications.store', $this->organisation), ['category' => 'Nomination',
                'external_nominee' => '1', 'nominee_name' => 'SBS nominee', 'visa_subclass' => $subclass])->assertSessionHasNoErrors();
            $nomination = $this->organisation->applications()->where('category', 'Nomination')->latest('id')->first();
            $this->assertSame($expected, $nomination->sponsorship_id);
        }
        $this->get(route('applications.show', $nomination))->assertOk()->assertViewHas('hasActiveSbs', true)
            ->assertSee($active->record_reference)->assertSee($expired->record_reference)->assertSee($labour->record_reference);
        $this->patch(route('applications.update', $nomination), $this->fields(['sponsorship_id' => $labour->id]))->assertSessionHasNoErrors();
        $foreign = Application::create(['organisation_id' => Organisation::create(['name' => 'Other employer'])->id,
            'category' => 'SBS', 'primary_counsellor_id' => $this->user->id]);
        $this->patchJson(route('applications.update', $nomination), $this->fields(['sponsorship_id' => $foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('sponsorship_id');
        $this->assertSame($labour->id, $nomination->fresh()->sponsorship_id);
    }

    public function test_lmt_and_notes_tabs_are_functional_and_respect_configuration(): void
    {
        $nomination = $this->nomination();
        $this->post(route('applications.activities.store', $nomination), ['kind' => 'lmt', 'title' => 'Advertise nominated role',
            'reference' => 'LMT-75', 'due_date' => '2026-12-01'])->assertSessionHasNoErrors();
        $this->get(route('applications.show', ['application' => $nomination, 'tab' => 'lmt']))->assertOk()->assertSee('Advertise nominated role');
        $this->get(route('applications.show', ['application' => $nomination, 'tab' => 'notes']))->assertOk()->assertSee('Add Note');
        ApplicationTypeSetting::create(['category' => 'Nomination', 'tabs' => ['tasks']]);
        $this->get(route('applications.show', ['application' => $nomination, 'tab' => 'lmt']))->assertOk()->assertViewHas('activeApplicationTab', 'tasks');
        $this->postJson(route('applications.activities.store', $nomination), ['kind' => 'lmt', 'title' => 'Disabled'])->assertUnprocessable();
        $this->assertDatabaseCount('application_activities', 1);
    }

    public function test_unassigned_users_cannot_view_or_change_nomination(): void
    {
        $nomination = $this->nomination();
        $this->actingAs(User::factory()->create(['account_type' => 'Admin']));
        $this->get(route('applications.show', $nomination))->assertNotFound();
        $this->patchJson(route('applications.update', $nomination), $this->fields())->assertNotFound();
        $this->patchJson(route('applications.organisation-details.update', $nomination), ['outcome' => 'Approved'])->assertNotFound();
    }
}
