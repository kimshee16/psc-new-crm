<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationTypeSetting;
use App\Models\ApplicationWorkflow;
use App\Models\Checkpoint;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckpointConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkpoints_follow_workflow_order_and_preserve_retired_values(): void
    {
        $admin = User::factory()->create();
        $record = Application::create([
            'category' => 'Study', 'primary_counsellor_id' => $admin->id,
            'organisation_id' => Organisation::create(['name' => 'Test organisation'])->id,
        ]);
        $this->actingAs($admin);
        $this->post(route('configuration.checkpoints.store'), [
            'name' => 'Supporting documents', 'status_lines' => "Requested\n\nVerified",
        ])->assertRedirect(route('configuration', ['tab' => 'checkpoints']));
        $documents = Checkpoint::firstOrFail();
        $letter = Checkpoint::create(['name' => 'Offer letter', 'statuses' => ['Received']]);
        $this->get(route('configuration', ['tab' => 'checkpoints']))->assertOk()->assertSeeInOrder(['Supporting documents', 'Requested', 'Verified']);
        $this->patch(route('configuration.workflow-checkpoints.update'), [
            'category' => 'Study', 'checkpoint_ids' => [$letter->id, $documents->id],
        ])->assertSessionHasNoErrors();
        $this->get(route('configuration', ['tab' => 'workflow-checkpoints']))->assertOk()->assertSee('Move up');
        $this->assertSame(ApplicationTypeSetting::DEFAULT_TABS, ApplicationTypeSetting::firstOrFail()->tabs);
        $this->get(route('applications.show', $record))->assertOk()->assertSeeInOrder([
            'Initial consultation', 'Offer letter', 'Supporting documents', 'Lodgement date', 'Outcome date',
        ]);
        $url = route('applications.checkpoints.update', $record);
        $this->patch($url, ['checkpoint_statuses' => [$documents->id => 'Verified']])->assertSessionHasNoErrors();
        $this->patch(route('configuration.checkpoints.update', $documents), [
            'name' => $documents->name, 'status_lines' => 'Requested',
        ])->assertSessionHasNoErrors();
        $this->get(route('applications.show', $record))->assertSee('Verified (retired)');
        $this->patchJson($url, ['checkpoint_statuses' => [$letter->id => 'Verified']])->assertUnprocessable();
        $this->delete(route('configuration.checkpoints.destroy', $documents))->assertRedirect();
        $this->get(route('applications.show', $record))->assertDontSee('Supporting documents');
        $this->assertSame('Verified', $record->fresh()->checkpoint_statuses[$documents->id]);
    }

    public function test_named_workflows_can_override_the_category_and_staff_cannot_configure_checkpoints(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->create(['account_type' => 'Counsellor']);
        $checkpoint = Checkpoint::create(['name' => 'Visa documents', 'statuses' => ['Received']]);
        $workflow = ApplicationWorkflow::create(['name' => 'Student visa', 'category' => 'Visa']);
        $category = ApplicationTypeSetting::create(['category' => 'Visa', 'tabs' => ['rfi']]);
        $category->forceFill(['checkpoint_ids' => [$checkpoint->id]])->save();
        $record = Application::create([
            'category' => 'Visa', 'application_workflow_id' => $workflow->id, 'primary_counsellor_id' => $staff->id,
            'organisation_id' => Organisation::create(['name' => 'Test organisation'])->id,
        ]);
        $this->actingAs($staff)->get(route('applications.show', $record))->assertOk()->assertSee($checkpoint->name);
        $this->patchJson(route('configuration.workflow-checkpoints.update'), ['workflow_id' => $workflow->id])->assertForbidden();
        $this->actingAs($admin)->patch(route('configuration.workflow-checkpoints.update'), ['workflow_id' => $workflow->id])->assertSessionHasNoErrors();
        $this->actingAs($staff)->get(route('applications.show', $record))->assertOk()->assertDontSee($checkpoint->name);
        $this->actingAs($admin)->patch(route('configuration.workflow-checkpoints.update'), [
            'workflow_id' => $workflow->id, 'inherit' => true,
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->get(route('applications.show', $record))->assertOk()->assertSee($checkpoint->name);
    }
}
