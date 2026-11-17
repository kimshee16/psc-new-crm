<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationRfi;
use App\Models\ApplicationTypeSetting;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationRfisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_each_application_type_has_an_rfi_tab_and_active_count(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (array_keys(ApplicationTypeSetting::types()) as $category) {
            $application = $this->application($user, $category);
            $application->rfis()->create($this->data($user));
            $application->rfis()->create($this->data($user, ['status' => 'Closed']));
            $this->get(route('applications.show', ['application' => $application, 'tab' => 'rfi']))
                ->assertOk()->assertSee('Add RFI')->assertSee('1 outstanding RFI')
                ->assertSee('RFI (s56 notice)')->assertSee('Closed')->assertSee('Open notes for RFI')
                ->assertViewHas('rfis', fn ($rfis) => $rfis->total() === 2);
        }
    }

    public function test_outstanding_badge_includes_every_non_closed_status_and_legacy_null_on_every_tab(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        foreach (ApplicationRfi::STATUSES as $status) {
            $application->rfis()->create($this->data($user, ['status' => $status]));
        }
        $application->rfis()->create($this->data($user, ['status' => null]));
        $application->activities()->create(['kind' => 'tasks', 'title' => 'Unrelated task', 'status' => 'To Do']);
        $this->application($user)->rfis()->create($this->data($user));
        // The count covers the application, independently of the RFI table page.
        for ($i = 0; $i < 10; $i++) {
            $application->rfis()->create($this->data($user, ['status' => 'Closed']));
        }
        $this->actingAs($user);
        foreach ([[], ['tab' => 'rfi'], ['tab' => 'rfi', 'page' => 2]] as $query) {
            $this->get(route('applications.show', ['application' => $application] + $query))
                ->assertOk()->assertSee('data-outstanding-rfi-count="5"', false)->assertSee('5 outstanding RFIs');
        }
    }

    public function test_creation_defaults_officer_and_status_and_stores_private_uploads(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->actingAs($user)->get(route('applications.rfis.create', $application))->assertOk()->assertSee('Add RFI');
        $this->post(route('applications.rfis.store', $application), $this->payload([
            'proof_of_lodgement' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            'lodged_date' => '2026-11-17', 'notes' => 'Initial note', 'return_to' => 'application',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('applications.show', ['application' => $application, 'tab' => 'rfi']).'#application-tabs');
        $rfi = ApplicationRfi::sole();
        $this->assertSame($application->id, $rfi->application_id);
        $this->assertSame($user->id, $rfi->assigned_to_user_id);
        $this->assertSame('Received', $rfi->status);
        $this->assertSame('Initial note', $rfi->notes);
        Storage::disk('local')->assertExists([$rfi->request_letter_path, $rfi->proof_of_lodgement_path]);
        $this->get(route('rfis.document', ['rfi' => $rfi, 'document' => 'request_letter']))->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')->assertHeader('content-type', 'application/pdf');
        $this->get(route('rfis.document', ['rfi' => $rfi, 'document' => 'proof_of_lodgement']))->assertOk();
        $this->get(route('rfis.document', ['rfi' => $rfi, 'document' => 'unknown']))->assertNotFound();
    }

    public function test_validation_is_atomic_and_other_requires_text(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->actingAs($user)->post(route('applications.rfis.store', $application), [])
            ->assertSessionHasErrors(['request_type', 'received_date', 'due_date', 'request_letter']);
        foreach ([
            ['request_type' => 'Other', 'request_type_other' => ''],
            ['request_type' => 'Unknown'], ['received_date' => 'bad'], ['due_date' => '2026-11-01'],
            ['assigned_to_user_id' => 999999], ['status' => 'Unknown'],
            ['request_letter' => UploadedFile::fake()->create('bad.html', 1, 'text/html')],
            ['request_letter' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')],
            ['proof_of_lodgement' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')],
        ] as $overrides) {
            $this->post(route('applications.rfis.store', $application), $this->payload($overrides))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('application_activities', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->post(route('applications.rfis.store', $application), $this->payload(['request_type' => 'Other', 'request_type_other' => 'Additional evidence']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Additional evidence', ApplicationRfi::sole()->request_label);
    }

    public function test_editing_retains_documents_and_replacements_remove_old_files(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $application = $this->application($user);
        $this->actingAs($user)->post(route('applications.rfis.store', $application), $this->payload())->assertSessionHasNoErrors();
        $rfi = ApplicationRfi::sole();
        $oldPath = $rfi->request_letter_path;
        $data = $this->data($other, ['status' => 'Awaiting client', 'due_date' => '2026-12-20']);
        $this->patch(route('rfis.update', $rfi), $data)->assertSessionHasNoErrors();
        $this->assertSame($oldPath, $rfi->fresh()->request_letter_path);
        $this->assertSame($other->id, $rfi->fresh()->assigned_to_user_id);
        $this->patch(route('rfis.update', $rfi), $data + ['request_letter' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf')])
            ->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($rfi->fresh()->request_letter_path);
    }

    public function test_closing_requires_confirmation_and_updates_count_and_list_visibility(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $rfi = $application->rfis()->create($this->data($user));
        $this->actingAs($user)->patch(route('rfis.update', $rfi), $this->data($user, ['status' => 'Closed']))
            ->assertSessionHasErrors('closed_confirmed');
        $this->assertSame('Received', $rfi->fresh()->status);
        $this->patch(route('rfis.update', $rfi), $this->data($user, ['status' => 'Closed', 'closed_confirmed' => '1']))
            ->assertSessionHasNoErrors();
        $this->get(route('applications.show', ['application' => $application, 'tab' => 'rfi']))->assertOk()->assertSee('0 outstanding RFIs');
        $this->get(route('rfis'))->assertViewHas('rfis', fn ($rows) => $rows->total() === 0);
        $this->get(route('rfis', ['display_closed' => 1]))->assertViewHas('rfis', fn ($rows) => $rows->total() === 1);
        $this->patch(route('rfis.update', $rfi), $this->data($user))->assertSessionHasNoErrors();
        $this->assertSame('Received', $rfi->fresh()->status);
        $this->get(route('applications.show', $application))->assertOk()->assertSee('1 outstanding RFI');
    }

    public function test_notes_are_editable_and_only_admins_can_delete_visible_rfis(): void
    {
        $user = User::factory()->create(['account_type' => 'Counsellor']);
        $admin = User::factory()->create(['account_type' => 'Admin']);
        $application = $this->application($user);
        $application->update(['secondary_counsellor_id' => $admin->id]);
        $this->actingAs($user)->post(route('applications.rfis.store', $application), $this->payload())->assertSessionHasNoErrors();
        $rfi = ApplicationRfi::sole();
        $this->patch(route('rfis.notes', $rfi), ['notes' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
        $this->get(route('rfis'))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->delete(route('rfis.destroy', $rfi))->assertForbidden();
        Storage::disk('local')->assertExists($rfi->request_letter_path);
        $this->actingAs($admin)->delete(route('rfis.destroy', $rfi))->assertRedirect(route('rfis'));
        $this->assertDatabaseMissing('application_activities', ['id' => $rfi->id]);
        Storage::disk('local')->assertMissing($rfi->request_letter_path);
        $this->get(route('applications.show', $application))->assertOk()->assertSee('0 outstanding RFIs');
    }

    public function test_all_record_actions_and_files_require_rfi_access_and_respect_disabled_tabs(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create(['account_type' => 'Admin']);
        $application = $this->application($owner);
        $rfi = $application->rfis()->create($this->data($owner));
        $this->get(route('rfis'))->assertRedirect(route('login'));
        $this->get(route('rfis.document', ['rfi' => $rfi, 'document' => 'request_letter']))->assertRedirect(route('login'));
        $this->actingAs($stranger)->get(route('rfis.edit', $rfi))->assertNotFound();
        $this->get(route('applications.rfis.create', $application))->assertNotFound();
        $this->patch(route('rfis.update', $rfi), $this->data($stranger))->assertNotFound();
        $this->patch(route('rfis.notes', $rfi), ['notes' => 'unauthorised'])->assertNotFound();
        $this->delete(route('rfis.destroy', $rfi))->assertNotFound();
        $this->get(route('rfis.document', ['rfi' => $rfi, 'document' => 'request_letter']))->assertNotFound();
        ApplicationTypeSetting::create(['category' => 'Study', 'tabs' => ['tasks']]);
        $this->actingAs($owner)->get(route('applications.rfis.create', $application))->assertStatus(422);
        $this->post(route('applications.rfis.store', $application), $this->payload())->assertStatus(422);
        $this->post(route('applications.activities.store', $application), ['kind' => 'rfi', 'title' => 'Bypass'])->assertNotFound();
        $this->assertSame('Received', $rfi->fresh()->status);
        $task = $application->activities()->create(['kind' => 'tasks', 'title' => 'Task']);
        $this->get(route('rfis.edit', $task))->assertNotFound();
    }

    public function test_migration_preserves_legacy_rfi_rows_and_other_activity_types(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $migration = require database_path('migrations/2026_11_16_000001_extend_application_rfis.php');
        $migration->down();
        $rfi = $application->activities()->create(['kind' => 'rfi', 'title' => 'Legacy evidence request', 'status' => 'Pending', 'due_date' => '2026-12-01', 'reference' => 'REF-12']);
        $task = $application->activities()->create(['kind' => 'tasks', 'title' => 'Existing task', 'status' => 'Pending']);
        $migration->up();
        $record = ApplicationRfi::findOrFail($rfi->id);
        $this->assertSame('Legacy evidence request', $record->request_label);
        $this->assertSame('Received', $record->status);
        $this->assertSame('REF-12', $record->reference);
        $this->assertSame('2026-12-01', $record->due_date->toDateString());
        $this->assertNull($record->received_date);
        $this->assertNull($record->request_letter_path);
        $this->assertSame('Pending', $task->fresh()->status);
    }

    public function test_generic_activity_endpoint_cannot_bypass_required_rfi_fields(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user, 'SBS');
        $this->actingAs($user)->post(route('applications.activities.store', $application), [
            'kind' => 'rfi', 'title' => 'Incomplete RFI',
        ])->assertSessionHasErrors('kind');
        $this->assertDatabaseCount('application_activities', 0);
    }

    private function application(User $user, string $category = 'Study'): Application
    {
        return Application::create(['category' => $category, 'primary_counsellor_id' => $user->id,
            'client_id' => Client::create(['client_id' => 8000 + Client::count(), 'first_name' => 'Rfi', 'surname' => 'Client', 'email' => 'rfi@example.test', 'mobile' => '', 'nationality' => '', 'current_location' => '', 'admin_office' => 'Sydney', 'primary_counsellor' => $user->name])->id,
            'organisation_id' => in_array($category, ['SBS', 'Nomination']) ? Organisation::create(['name' => 'Employer'])->id : null]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['request_type' => 'RFI (s56 notice)', 'received_date' => '2026-11-16', 'due_date' => '2026-12-01',
            'request_letter' => UploadedFile::fake()->createWithContent('request.pdf', "%PDF-1.4\n%%EOF")], $overrides);
    }

    private function data(User $user, array $overrides = []): array
    {
        return array_replace(['request_type' => 'RFI (s56 notice)', 'received_date' => '2026-11-16', 'due_date' => '2026-12-01',
            'assigned_to_user_id' => $user->id, 'status' => 'Received', 'request_letter_path' => 'rfis/request.pdf'], $overrides);
    }
}
