<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Office;
use App\Models\Organisation;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePrivilegesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_permissions_gate_direct_routes_embedded_notes_and_navigation(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->create(['account_type' => 'Counsellor']);
        $application = Application::create([
            'category' => 'Study', 'primary_counsellor_id' => $staff->id,
            'organisation_id' => Organisation::create(['name' => 'Test organisation'])->id, 'notes' => 'Private test note',
        ]);
        $this->actingAs($admin)->get(route('role-privileges'))->assertOk()
            ->assertSeeInOrder(['Administrator', 'System Administrator', 'Regional Manager', 'Office Manager', 'Staff']);
        $settings = route('role-privileges.update', 'Counsellor');
        $this->patch($settings, [
            'permissions' => ['applications.view', 'clients.create', 'rfis.view'],
            'record_scope' => 'assigned', 'notes_access' => 'hidden',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('role_privileges', ['account_type' => 'Counsellor', 'notes_access' => 'hidden']);
        $this->actingAs($staff)->get(route('applications.show', $application))->assertOk()
            ->assertDontSee('Private test note')->assertDontSee('Save summary')
            ->assertDontSee('href="'.route('clients').'"', false);
        $this->get(route('clients.create'))->assertForbidden();
        $this->get(route('applications.export'))->assertForbidden();
        $this->patchJson(route('applications.update', $application), ['status' => 'Finalised'])->assertForbidden();
        $this->postJson(route('notes.store', ['module' => 'applications', 'recordId' => $application->id]), [
            'body' => 'Denied',
        ])->assertForbidden();
        $this->get(route('role-privileges'))->assertForbidden();
        $this->patchJson($settings, ['record_scope' => 'existing', 'notes_access' => 'write'])->assertForbidden();
    }

    public function test_region_scope_includes_offices_in_managed_regions_and_excludes_other_assignments(): void
    {
        $admin = User::factory()->create();
        $manager = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_REGIONAL_MANAGER, 'offices' => ['MEL']]);
        $regional = User::factory()->create(['offices' => ['BNE']]);
        $outside = User::factory()->create(['offices' => ['SYD']]);
        $region = Region::create(['name' => 'Managed test region', 'regional_manager_id' => $manager->id]);
        $region->offices()->sync([Office::where('code', 'BNE')->firstOrFail()->id]);
        $organisation = Organisation::create(['name' => 'Example organisation']);
        $inside = Application::create(['category' => 'Study', 'primary_counsellor_id' => $regional->id, 'organisation_id' => $organisation->id]);
        $other = Application::create(['category' => 'Study', 'primary_counsellor_id' => $outside->id, 'organisation_id' => $organisation->id]);
        $this->actingAs($admin)->patch(route('role-privileges.update', User::ACCOUNT_TYPE_REGIONAL_MANAGER), [
            'permissions' => ['applications.view'], 'record_scope' => 'region', 'notes_access' => 'read',
        ])->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('applications.show', $inside))->assertOk();
        $this->get(route('applications.show', $other))->assertNotFound();
        $this->patchJson(route('applications.update', $inside), ['status' => 'Finalised'])->assertForbidden();
        $this->actingAs($admin)->patchJson(route('role-privileges.update', 'Super Admin'), [
            'record_scope' => 'assigned', 'notes_access' => 'hidden',
        ])->assertForbidden();
    }
}
