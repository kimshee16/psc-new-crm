<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationWorkflow;
use App\Models\MigrationAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrationAgentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
    }

    private function details(array $overrides = []): array
    {
        return array_replace(['first_name' => 'Ana', 'last_name' => 'Santos', 'registration_number' => '0209467',
            'email' => 'ANA@EXAMPLE.TEST', 'user_id' => null], $overrides);
    }

    private function createAgent(array $overrides = []): MigrationAgent
    {
        $this->post(route('configuration.migration-agents.store'), $this->details($overrides)
            + ['signature' => UploadedFile::fake()->image('signature.png')])->assertSessionHasNoErrors();

        return MigrationAgent::latest('id')->firstOrFail();
    }

    public function test_admin_can_open_empty_list_and_add_form_and_create_the_reference_fields(): void
    {
        $url = route('configuration', ['tab' => 'migration-agents']);
        $this->get($url)->assertOk()->assertSee('No migration agents configured');
        $this->get($url.'&action=create')->assertOk()->assertSee('First Name')->assertSee('Last Name')
            ->assertSee('Registration Number')->assertSee('Email')->assertSee('type="file"', false)
            ->assertDontSee('Draw your signature');
        $agent = $this->createAgent();
        $this->assertSame('Ana Santos', $agent->name);
        $this->assertSame('0209467', $agent->registration_number);
        $this->assertSame('ana@example.test', $agent->email);
        Storage::disk('local')->assertExists($agent->signature_path);
        $this->get($url)->assertOk()->assertSee('Ana Santos')->assertSee('0209467')
            ->assertSeeInOrder(['Agent Name', 'Signature', 'Registration Number', 'Email', 'Actions']);
        $this->get($url.'&edit='.$agent->id)->assertOk()->assertSee('Edit Migration Agent')
            ->assertSee('Leave blank to keep the current signature.');
        $this->get($url.'&edit=999999')->assertNotFound();
    }

    public function test_email_and_crm_user_are_optional_and_existing_user_permissions_are_preserved(): void
    {
        $user = User::factory()->create(['account_type' => 'Counsellor', 'name' => 'Existing CRM Name']);
        $workflow = ApplicationWorkflow::create(['name' => 'Visa workflow', 'category' => 'Visa', 'migration_agent_id' => $user->id]);
        $application = Application::create(['category' => 'Visa', 'migration_agent_id' => $user->id]);
        $agent = $this->createAgent(['email' => '', 'user_id' => (string) $user->id]);
        $this->assertNull($agent->email);
        $this->assertSame($agent->id, $workflow->fresh()->migration_agent_details->id);
        $this->assertSame($agent->id, $application->fresh()->migration_agent_details->id);
        $this->assertSame($agent->id, $user->fresh()->migrationAgentDetails->id);
        $this->assertSame($user->id, $workflow->fresh()->migration_agent_id);
        $this->assertSame('Existing CRM Name', $user->fresh()->name);
        $this->assertSame('Counsellor', $user->fresh()->account_type);
    }

    public function test_edit_keeps_signature_until_replaced_and_cleans_up_old_upload(): void
    {
        $agent = $this->createAgent();
        $old = $agent->signature_path;
        $this->patch(route('configuration.migration-agents.update', $agent), $this->details(['first_name' => 'Maria']))
            ->assertSessionHasNoErrors();
        $this->assertSame($old, $agent->fresh()->signature_path);
        $this->patch(route('configuration.migration-agents.update', $agent), $this->details()
            + ['signature' => UploadedFile::fake()->image('replacement.jpg')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($agent->fresh()->signature_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('migration-agent-signatures'));
    }

    public function test_invalid_fields_and_unsafe_uploads_do_not_save_records_or_files(): void
    {
        $this->postJson(route('configuration.migration-agents.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name', 'registration_number', 'signature']);
        foreach ([['first_name' => ''], ['last_name' => ['bad']], ['email' => 'bad'], ['user_id' => 999999],
            ['registration_number' => str_repeat('1', 41)],
            ['signature' => UploadedFile::fake()->create('signature.svg', 1, 'image/svg+xml')],
            ['signature' => UploadedFile::fake()->create('signature.html', 1, 'text/html')],
            ['signature' => UploadedFile::fake()->image('large.png')->size(2049)]] as $invalid) {
            $payload = $this->details() + ['signature' => UploadedFile::fake()->image('valid.png')];
            $this->postJson(route('configuration.migration-agents.store'), array_replace($payload, $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('migration_agents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_duplicate_registration_and_crm_user_are_rejected_without_replacing_existing_files(): void
    {
        $user = User::factory()->create();
        $first = $this->createAgent(['user_id' => $user->id]);
        $second = $this->createAgent(['registration_number' => '0317320']);
        $this->postJson(route('configuration.migration-agents.store'), $this->details()
            + ['signature' => UploadedFile::fake()->image('duplicate.png')])->assertUnprocessable()->assertJsonValidationErrors('registration_number');
        $this->patchJson(route('configuration.migration-agents.update', $second), $this->details([
            'registration_number' => '0317320', 'user_id' => $user->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertDatabaseCount('migration_agents', 2);
        Storage::disk('local')->assertExists([$first->signature_path, $second->signature_path]);
    }

    public function test_private_signature_access_is_admin_only_and_missing_file_is_a_404(): void
    {
        $agent = $this->createAgent();
        $url = route('configuration.migration-agents.signature', $agent);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create(['account_type' => 'Counsellor']))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['account_type' => 'Super Admin']));
        Storage::disk('local')->delete($agent->signature_path);
        $this->get($url)->assertNotFound();
    }

    public function test_used_profiles_cannot_be_removed_or_relinked_but_details_can_be_updated(): void
    {
        $user = User::factory()->create();
        $replacement = User::factory()->create();
        $agent = $this->createAgent(['user_id' => $user->id]);
        $workflow = ApplicationWorkflow::create(['name' => 'Visa', 'category' => 'Visa', 'migration_agent_id' => $user->id]);
        $this->delete(route('configuration.migration-agents.destroy', $agent))->assertSessionHasErrors('migration_agent');
        $this->patch(route('configuration.migration-agents.update', $agent), $this->details(['user_id' => $replacement->id]))
            ->assertSessionHasErrors('user_id');
        $this->patch(route('configuration.migration-agents.update', $agent), $this->details(['user_id' => (string) $user->id, 'first_name' => 'Updated']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Updated', $agent->fresh()->first_name);
        $workflow->delete();
        Application::create(['category' => 'Visa', 'migration_agent_id' => $user->id]);
        $this->delete(route('configuration.migration-agents.destroy', $agent))->assertSessionHasErrors('migration_agent');
        Storage::disk('local')->assertExists($agent->signature_path);
    }

    public function test_unused_agents_can_be_removed_and_signature_is_deleted(): void
    {
        $agent = $this->createAgent();
        $path = $agent->signature_path;
        $this->delete(route('configuration.migration-agents.destroy', $agent))->assertSessionHasNoErrors()
            ->assertRedirect(route('configuration', ['tab' => 'migration-agents']));
        $this->assertDatabaseMissing('migration_agents', ['id' => $agent->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_failed_upload_preserves_existing_record_and_signature(): void
    {
        $agent = $this->createAgent();
        $path = $agent->signature_path;
        Storage::shouldReceive('disk')->with('local')->andReturnSelf();
        Storage::shouldReceive('putFileAs')->once()->andReturn(false);
        $this->patch(route('configuration.migration-agents.update', $agent), $this->details(['first_name' => 'Must not save'])
            + ['signature' => UploadedFile::fake()->image('replacement.png')])->assertStatus(500);
        $this->assertSame('Ana', $agent->fresh()->first_name);
        $this->assertSame($path, $agent->fresh()->signature_path);
    }

    public function test_guests_and_non_admin_roles_cannot_manage_agents_or_view_configuration(): void
    {
        $agent = $this->createAgent();
        foreach (['Counsellor', 'Manager', 'Regional manager'] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]));
            $this->get(route('configuration', ['tab' => 'migration-agents']))->assertForbidden();
            $this->post(route('configuration.migration-agents.store'), $this->details())->assertForbidden();
            $this->patch(route('configuration.migration-agents.update', $agent), $this->details())->assertForbidden();
            $this->delete(route('configuration.migration-agents.destroy', $agent))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('configuration.migration-agents.signature', $agent))->assertRedirect(route('login'));
        $this->post(route('configuration.migration-agents.store'), $this->details())->assertRedirect(route('login'));
    }
}
