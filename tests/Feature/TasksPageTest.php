<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Office;
use App\Models\Organisation;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TasksPageTest extends TestCase
{
    use RefreshDatabase;

    private User $counsellor;

    private User $colleague;

    private User $outside;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        $this->counsellor = User::factory()->create(['name' => 'Jane Sydney', 'account_type' => 'Counsellor', 'offices' => ['SYD']]);
        $this->colleague = User::factory()->create(['name' => 'Bob Sydney', 'account_type' => 'Counsellor', 'offices' => ['SYD']]);
        $this->outside = User::factory()->create(['name' => 'Melbourne Staff', 'account_type' => 'Counsellor', 'offices' => ['MEL']]);
        $this->actingAs($this->counsellor);
    }

    private function task(User $assignee, array $data = []): ApplicationActivity
    {
        return ApplicationActivity::create($data + ['kind' => 'tasks', 'title' => 'Task for '.$assignee->name,
            'assigned_to_user_id' => $assignee->id, 'status' => 'To Do', 'due_date' => '2026-11-20']);
    }

    private function payload(array $data = []): array
    {
        return $data + ['title' => 'Prepare visa evidence', 'due_date' => '2026-11-13',
            'assigned_to_user_id' => $this->counsellor->id, 'status' => 'To Do', '_add_task' => '1'];
    }

    private function lead(string $reference): Lead
    {
        $client = Client::create(['client_id' => preg_replace('/\D/', '', $reference), 'first_name' => 'Test', 'surname' => 'Client',
            'mobile' => '0400000000', 'email' => 'client@example.test', 'nationality' => 'Australia',
            'current_location' => 'Sydney', 'admin_office' => 'Sydney', 'primary_counsellor' => $this->counsellor->name]);

        return Lead::create(['lead_id' => preg_replace('/\D/', '', $reference), 'client_id' => $client->id, 'first_name' => 'Test', 'surname' => 'Client',
            'assigned_user_id' => $this->counsellor->id, 'assigned_name' => $this->counsellor->name,
            'assigned_office_code' => 'SYD', 'assigned_office' => 'Sydney', 'assignment_reason' => 'Test',
            'form_type' => 'Contact', 'source' => 'Test', 'email' => 'client@example.test',
            'phone_country_code' => '+61', 'phone_number' => '400000000', 'mobile' => '0400000000',
            'nationality' => 'Australia', 'current_location' => 'Sydney', 'submitted_at' => now()]);
    }

    public function test_counsellors_see_only_their_assigned_tasks_even_when_they_created_other_tasks(): void
    {
        $own = $this->task($this->counsellor);
        $this->task($this->colleague);
        $this->task($this->outside);
        $this->task($this->counsellor, ['kind' => 'rfi', 'title' => 'Not a task']);
        $this->get(route('tasks'))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$own->id]);
        $this->get(route('tasks', ['assignee' => [$this->outside->id]]))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->isEmpty());
    }

    public function test_managers_see_office_staff_tasks_and_own_tasks_with_no_office_fail_open(): void
    {
        $this->task($this->counsellor);
        $this->task($this->outside);
        $unassigned = $this->task($this->outside, ['title' => 'Unassigned legacy task']);
        $unassigned->update(['assigned_to_user_id' => null]);
        $manager = User::factory()->create(['account_type' => 'Manager', 'offices' => ['SYD']]);
        $this->task($manager);
        $this->actingAs($manager)->get(route('tasks'))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 2);
        $manager->update(['offices' => []]);
        $this->get(route('tasks'))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 1);
    }

    public function test_regional_managers_use_region_memberships_and_admins_see_all_offices(): void
    {
        $manager = User::factory()->create(['account_type' => 'Regional manager', 'offices' => []]);
        $region = Region::create(['name' => 'NSW', 'regional_manager_id' => $manager->id]);
        $region->offices()->attach(Office::where('code', 'SYD')->firstOrFail());
        $this->task($this->counsellor);
        $this->task($this->outside);
        $unassigned = $this->task($this->outside, ['title' => 'Unassigned legacy task']);
        $unassigned->update(['assigned_to_user_id' => null]);
        $this->actingAs($manager)->get(route('tasks'))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 1);
        foreach (['Admin', 'Super Admin'] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role, 'offices' => []]))
                ->get(route('tasks'))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 3);
        }
    }

    public function test_filters_combine_with_multi_selects_and_legacy_statuses(): void
    {
        $application = Application::create(['category' => 'Study', 'primary_counsellor_id' => $this->outside->id]);
        $target = $this->task($this->counsellor, ['application_id' => $application->id, 'title' => 'Review 100%_visa evidence', 'status' => 'Pending']);
        $this->task($this->counsellor, ['title' => 'Review 100x_visa evidence', 'status' => 'Done']);
        $this->task($this->outside, ['title' => 'Review 100%_visa evidence', 'application_id' => $application->id]);
        $filters = ['description' => '100%_', 'relates_to' => substr($application->record_reference, 0, 7),
            'assignee' => [$this->counsellor->id, $this->colleague->id], 'creator' => [$this->counsellor->id], 'status' => ['To Do', 'In Progress']];
        $this->get(route('tasks', $filters))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$target->id]);
        $this->get(route('tasks', ['relates_to' => str_pad((string) $application->id, 5, '0', STR_PAD_LEFT)]))
            ->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$target->id]);
        $this->get(route('tasks', ['status' => ['Done']]))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 1);
    }

    public function test_lead_reference_search_and_null_dates_work_with_sort_and_pagination(): void
    {
        $lead = $this->lead('LEAD-9876');
        $early = $this->task($this->counsellor, ['lead_id' => $lead->id, 'due_date' => '2026-11-14']);
        $late = $this->task($this->counsellor, ['due_date' => '2026-12-01']);
        $undated = $this->task($this->counsellor, ['due_date' => null]);
        $this->get(route('tasks', ['relates_to' => '987']))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$early->id]);
        $this->get(route('tasks', ['direction' => 'desc']))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$late->id, $early->id, $undated->id]);
        for ($i = 0; $i < 20; $i++) {
            $this->task($this->counsellor);
        }
        $this->get(route('tasks', ['per_page' => 20, 'page' => 2]))->assertOk()
            ->assertViewHas('tasks', fn ($tasks) => $tasks->count() === 3 && $tasks->total() === 23 && $tasks->perPage() === 20);
    }

    public function test_popup_creates_standalone_tasks_with_trusted_creator_and_all_four_statuses(): void
    {
        foreach (ApplicationActivity::TASK_STATUSES as $status) {
            $this->post(route('tasks.store'), $this->payload(['status' => $status, 'created_by_user_id' => $this->outside->id, 'kind' => 'payments']))
                ->assertSessionHasNoErrors()->assertRedirect(route('tasks'));
        }
        $this->assertDatabaseCount('application_activities', 4);
        $this->assertSame(4, ApplicationActivity::where('kind', 'tasks')->where('created_by_user_id', $this->counsellor->id)->whereNull('application_id')->count());
        $this->get(route('tasks'))->assertOk()->assertSee('task-dialog')->assertSee('Pending External')->assertSee('Jane Sydney');
    }

    public function test_invalid_dates_assignees_and_statuses_are_rejected_and_draft_restores(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        $restricted = User::factory()->create(['portal_invite_status' => 'Restrict Access']);
        foreach ([['due_date' => '2026-11-12'], ['due_date' => '2026-02-30'], ['due_date' => null],
            ['title' => ''], ['status' => 'Pending'], ['assigned_to_user_id' => 999999],
            ['assigned_to_user_id' => $inactive->id], ['assigned_to_user_id' => $restricted->id]] as $invalid) {
            $response = $this->postJson(route('tasks.store'), array_replace($this->payload(), $invalid));
            $this->assertSame(422, $response->status(), json_encode($invalid).$response->getContent());
        }
        $this->from(route('tasks'))->post(route('tasks.store'), $this->payload(['due_date' => '2026-11-12']))->assertSessionHasErrors('due_date');
        $this->get(route('tasks'))->assertOk()->assertSee('value="Prepare visa evidence"', false)->assertSee('dialog.showModal()', false);
        $this->assertDatabaseCount('application_activities', 0);
    }

    public function test_optional_links_use_existing_record_permissions(): void
    {
        $application = Application::create(['category' => 'Study']);
        $lead = $this->lead('LEAD-1234');
        $this->post(route('tasks.store'), $this->payload(['related_type' => 'application', 'related_id' => $application->record_reference]))->assertSessionHasNoErrors();
        $this->post(route('tasks.store'), $this->payload(['related_type' => 'lead', 'related_id' => $lead->lead_id]))->assertSessionHasNoErrors();
        $hidden = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $this->outside->id]);
        $this->postJson(route('tasks.store'), $this->payload(['related_type' => 'application', 'related_id' => (string) $hidden->id]))->assertUnprocessable();
        $this->postJson(route('tasks.store'), $this->payload(['related_type' => 'application', 'related_id' => 'NOM-0001']))->assertUnprocessable();
        $this->postJson(route('tasks.store'), $this->payload(['related_type' => 'application', 'related_id' => '0']))->assertUnprocessable();
        $this->postJson(route('tasks.store'), $this->payload(['related_type' => 'lead', 'related_id' => 'missing']))->assertUnprocessable();
        $this->assertDatabaseCount('application_activities', 2);
    }

    public function test_csv_exports_all_filtered_rows_in_display_order_with_formula_protection(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->task($this->counsellor, ['title' => '=Review '.$i]);
        }
        $this->task($this->counsellor, ['status' => 'Done', 'title' => 'Excluded by filter']);
        $this->task($this->outside, ['title' => 'Hidden export']);
        $response = $this->get(route('tasks.export', ['status' => ['To Do'], 'per_page' => 10, 'page' => 2]));
        $response->assertOk()->assertDownload('Tasks -20261113120000.csv');
        $csv = $response->streamedContent();
        $this->assertSame(12, substr_count($csv, "'=Review"));
        $this->assertStringNotContainsString('Hidden export', $csv);
        $this->assertStringNotContainsString('Excluded by filter', $csv);
        $this->assertStringContainsString('Task created by', $csv);
    }

    public function test_organisation_tasks_appear_once_with_their_actual_creator_and_assignee(): void
    {
        $organisation = Organisation::create(['name' => 'Employer', 'created_by_user_id' => $this->counsellor->id]);
        $application = $organisation->applications()->create(['category' => 'SBS']);
        $this->post(route('organisations.tasks.store', $organisation), $this->payload(['application_id' => $application->id, 'assigned_to_user_id' => $this->colleague->id]))->assertSessionHasNoErrors();
        $task = $application->activities()->sole();
        $this->assertSame($this->counsellor->id, $task->created_by_user_id);
        $this->actingAs($this->colleague)->get(route('tasks'))->assertOk()->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$task->id]);
    }

    public function test_legacy_backfill_assigns_application_tasks_without_inventing_creators(): void
    {
        $application = Application::create(['category' => 'Visa']);
        $id = DB::table('application_activities')->insertGetId(['application_id' => $application->id, 'kind' => 'tasks', 'title' => 'Legacy', 'status' => 'Pending']);
        $migration = require database_path('migrations/2026_11_13_000001_extend_tasks_for_global_list.php');
        $migration->down();
        $migration->up();
        $legacy = ApplicationActivity::findOrFail($id);
        $this->assertSame($this->counsellor->id, $legacy->assigned_to_user_id);
        $this->assertNull($legacy->created_by_user_id);
        $this->get(route('tasks'))->assertOk()->assertSee('Unknown (legacy task)')->assertSee('To Do');
    }

    public function test_invalid_filters_and_malformed_drafts_fail_without_breaking_the_page(): void
    {
        foreach (['tasks', 'tasks.export'] as $route) {
            foreach ([['description' => ['unexpected']], ['direction' => 'invalid'], ['per_page' => 1000],
                ['assignee' => 'all'], ['creator' => ['invalid']], ['status' => ['invalid']]] as $filters) {
                $this->getJson(route($route, $filters))->assertUnprocessable();
            }
        }
        $this->from(route('tasks'))->post(route('tasks.store'), $this->payload(['title' => ['bad'],
            'due_date' => ['bad'], 'assigned_to_user_id' => ['bad'], 'related_id' => ['bad']]))->assertSessionHasErrors();
        $this->get(route('tasks'))->assertOk();
    }

    public function test_guests_and_restricted_accounts_cannot_access_tasks_or_export(): void
    {
        auth()->logout();
        foreach (['tasks', 'tasks.export'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->post(route('tasks.store'), $this->payload())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_active' => false]))->get(route('tasks'))->assertRedirect(route('login'));
    }
}
