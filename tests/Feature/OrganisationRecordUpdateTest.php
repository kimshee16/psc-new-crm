<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationTypeSetting;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationRecordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function source(User $owner, ?Organisation $organisation = null, array $data = []): Application
    {
        return ($organisation ?? Organisation::create(['name' => 'Acme Legal', 'created_by_user_id' => $owner->id]))
            ->applications()->create($data + ['category' => 'SBS', 'sponsor_type' => 'Standard Business Sponsor', 'primary_counsellor_id' => $owner->id]);
    }

    public function test_tasks_can_be_added_edited_completed_and_deleted_without_changing_the_linked_record(): void
    {
        $user = User::factory()->create();
        $staff = User::factory()->create();
        $source = $this->source($user);
        $other = $this->source($user, $source->organisation);
        $this->actingAs($user)->post(route('organisations.tasks.store', $source->organisation), [
            'application_id' => $source->id, 'title' => 'Prepare document', 'status' => 'Pending',
            'due_date' => '2026-12-15', 'assigned_to_user_id' => $staff->id, 'kind' => 'quotes', 'amount' => 100,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $task = $source->activities()->sole();
        $this->assertSame('tasks', $task->kind);
        $this->assertNull($task->amount);
        $this->get(route('organisations.show', $source->organisation))->assertOk()
            ->assertSee('Prepare document')->assertSee($staff->name)->assertSee('15 Dec 2026')
            ->assertSee('Edit task Prepare document')->assertSee('Delete task Prepare document');
        $this->patch(route('organisations.tasks.update', [$source->organisation, $task]), [
            'title' => 'Obtain police clearance', 'status' => 'In progress', 'due_date' => null,
            'assigned_to_user_id' => $user->id, 'application_id' => $other->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($source->id, $task->fresh()->application_id);
        $this->assertNull($task->fresh()->due_date);
        $this->patch(route('organisations.tasks.update', [$source->organisation, $task]), ['status' => 'Done'])->assertSessionHasNoErrors();
        $this->assertSame('Done', $task->fresh()->status);
        $this->assertSame('Obtain police clearance', $task->fresh()->title);
        $this->delete(route('organisations.tasks.destroy', [$source->organisation, $task]))->assertRedirect();
        $this->assertDatabaseMissing('application_activities', ['id' => $task->id]);
        $this->assertDatabaseCount('applications', 2);
    }

    public function test_task_mutations_enforce_organisation_application_assignment_and_kind(): void
    {
        $user = User::factory()->create();
        $source = $this->source($user);
        $hidden = $this->source(User::factory()->create(), $source->organisation);
        $unrelated = $this->source($user);
        $study = $this->source($user, $source->organisation, ['category' => 'Study']);
        $this->actingAs($user);
        foreach ([$hidden, $unrelated, $study] as $target) {
            $task = $target->activities()->create(['kind' => 'tasks', 'title' => 'Hidden task', 'status' => 'Pending']);
            $this->postJson(route('organisations.tasks.store', $source->organisation), ['application_id' => $target->id])->assertNotFound();
            $this->patchJson(route('organisations.tasks.update', [$source->organisation, $task]), ['status' => 'Done'])->assertNotFound();
            $this->deleteJson(route('organisations.tasks.destroy', [$source->organisation, $task]))->assertNotFound();
        }
        $payment = $source->activities()->create(['kind' => 'payments', 'title' => 'Fee']);
        $this->deleteJson(route('organisations.tasks.destroy', [$source->organisation, $payment]))->assertNotFound();
        $this->get(route('organisations.show', $source->organisation))->assertOk()->assertDontSee('Hidden task');
    }

    public function test_invalid_tasks_and_disabled_types_do_not_create_records_and_drafts_are_restored(): void
    {
        $user = User::factory()->create();
        $source = $this->source($user);
        $valid = ['application_id' => $source->id, 'title' => 'Draft task', 'status' => 'Pending', 'assigned_to_user_id' => $user->id];
        $this->actingAs($user);
        foreach ([['title' => ''], ['status' => 'Invalid'], ['due_date' => '2026-02-30'],
            ['assigned_to_user_id' => null], ['assigned_to_user_id' => 99999],
            ['assigned_to_user_id' => User::factory()->create(['is_active' => false])->id]] as $invalid) {
            $this->postJson(route('organisations.tasks.store', $source->organisation), array_replace($valid, $invalid))->assertUnprocessable();
        }
        $this->from(route('organisations.show', $source->organisation))->post(route('organisations.tasks.store', $source->organisation),
            array_replace($valid, ['_task_form' => 'new', 'assigned_to_user_id' => null]))->assertSessionHasErrors('assigned_to_user_id');
        $this->get(route('organisations.show', $source->organisation))->assertOk()->assertSee('value="Draft task"', false);
        ApplicationTypeSetting::create(['category' => 'SBS', 'tabs' => []]);
        $this->postJson(route('organisations.tasks.store', $source->organisation), $valid)->assertUnprocessable();
        $this->assertDatabaseCount('application_activities', 0);
    }

    public function test_notes_collate_assigned_sponsorship_and_nomination_records_and_escape_content(): void
    {
        $user = User::factory()->create();
        $source = $this->source($user, null, ['notes' => '<script>legacy note</script>']);
        $this->source($user, $source->organisation, ['category' => 'Nomination', 'notes' => json_encode([
            ['body' => 'Nomination note', 'author' => 'Michelle', 'datetime' => '2026-11-05T12:00:00Z'],
        ])]);
        $this->source(User::factory()->create(), $source->organisation, ['notes' => 'Confidential note']);
        $this->source($user, $source->organisation, ['category' => 'Visa', 'notes' => 'Visa note']);
        $this->source($user, null, ['notes' => 'Unrelated note']);
        $this->actingAs($user)->get(route('organisations.show', ['organisation' => $source->organisation, 'tab' => 'notes']))
            ->assertOk()->assertSee('<script>legacy note</script>')->assertDontSee('<script>legacy note</script>', false)
            ->assertSee('Nomination note')->assertSee('Michelle')->assertDontSee('Confidential note')
            ->assertDontSee('Visa note')->assertDontSee('Unrelated note')->assertViewHas('counts', fn ($counts) => $counts['notes'] === 2);
    }

    public function test_agreements_and_existing_quotes_are_kept_separate_with_labour_sponsorship_references(): void
    {
        $user = User::factory()->create();
        $source = $this->source($user, null, ['sponsor_type' => 'Labour Agreement']);
        $this->actingAs($user);
        foreach (['agreements' => 'Employer agreement', 'quotes' => 'Service quote'] as $kind => $title) {
            $this->post(route('applications.activities.store', $source), ['kind' => $kind, 'title' => $title]
                + ($kind === 'quotes' ? ['amount' => 450, 'currency' => 'AUD'] : []))->assertSessionHasNoErrors();
            $this->get(route('applications.show', ['application' => $source, 'tab' => $kind]))->assertOk()->assertSee($title);
            $this->get(route('organisations.show', ['organisation' => $source->organisation, 'tab' => $kind]))->assertOk()->assertSee($title);
        }
        $this->get(route('organisations.show', ['organisation' => $source->organisation, 'tab' => 'agreements']))
            ->assertSee('LAB-0001')->assertSee('Agreements')->assertSee('Quotes')->assertDontSee('Service quote');
        $this->assertDatabaseCount('application_activities', 2);
    }
}
