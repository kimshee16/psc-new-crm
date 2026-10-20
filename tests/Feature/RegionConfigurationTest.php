<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_groupings_and_add_and_edit_forms_are_available_to_admins(): void
    {
        $admin = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]);
        $manager = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_REGIONAL_MANAGER]);
        $this->actingAs($admin)->get('/configuration?tab=regions')->assertOk()
            ->assertSeeInOrder(['Region Name', 'Regional Manager', 'Offices', 'AUS', 'LATAM', 'Philippines']);

        foreach (['AUS' => ['MEL', 'SYD'], 'LATAM' => ['BRA', 'LATAM'], 'Philippines' => ['MNL', 'PHIL-ONSHORE']] as $name => $codes) {
            $this->assertSame($codes, Region::where('name', $name)->firstOrFail()->offices->pluck('code')->all());
        }

        $this->get('/configuration?tab=regions&action=create')->assertOk()
            ->assertSee('Submit and Exit')->assertSee($manager->email);
        $this->get('/configuration?tab=regions&edit='.Region::first()->id)->assertOk()->assertSee('Edit Region');
        $this->get('/configuration?tab=regions&edit=999999')->assertNotFound();
        $this->get('/configuration?tab=regions&sort=name&direction=desc')->assertOk()
            ->assertViewHas('regions', fn ($regions) => $regions->pluck('name')->all() === ['Philippines', 'LATAM', 'AUS']);
    }

    public function test_create_and_edit_persist_offices_and_optional_manager(): void
    {
        $this->actingAs(User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]));
        $manager = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_REGIONAL_MANAGER]);
        $officeIds = Office::whereIn('code', ['JKT', 'DPS'])->pluck('id')->all();
        $this->post('/configuration/regions', [
            'name' => '  Indonesia  ', 'offices' => $officeIds, 'regional_manager_id' => $manager->id,
        ])->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=regions');

        $region = Region::where('name', 'Indonesia')->firstOrFail();
        $this->assertEqualsCanonicalizing($officeIds, $region->offices->modelKeys());
        $this->assertSame($manager->id, $region->regionalManager->id);

        $this->patch('/configuration/regions/'.$region->id, [
            'name' => 'Indonesia Updated', 'offices' => [$officeIds[0]], 'regional_manager_id' => '',
        ])->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=regions');
        $this->assertSame([$officeIds[0]], $region->fresh()->offices->modelKeys());
        $this->assertNull($region->fresh()->regional_manager_id);
        $this->assertDatabaseHas('regions', ['id' => $region->id, 'name' => 'Indonesia Updated']);
    }

    public function test_invalid_changes_preserve_existing_region_and_return_form_input(): void
    {
        $this->actingAs(User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]));
        $region = Region::where('name', 'AUS')->firstOrFail();
        $officeId = Office::where('code', 'MEL')->value('id');
        $counsellor = User::factory()->create(['account_type' => 'Counsellor']);
        $formUrl = '/configuration?tab=regions&edit='.$region->id;
        $payload = ['name' => 'Renamed', 'offices' => [$officeId], 'regional_manager_id' => null];
        foreach ([
            ['name', '   ', 'name'], ['name', 'LATAM', 'name'], ['name', str_repeat('a', 121), 'name'],
            ['offices', [], 'offices'], ['offices', [999999], 'offices.0'],
            ['offices', [$officeId, $officeId], 'offices.0'],
            ['regional_manager_id', $counsellor->id, 'regional_manager_id'],
            ['regional_manager_id', 999999, 'regional_manager_id'],
        ] as [$field, $value, $error]) {
            $this->from($formUrl)->patch('/configuration/regions/'.$region->id, array_replace($payload, [$field => $value]))
                ->assertRedirect($formUrl)->assertSessionHasErrors($error);
        }
        $this->get($formUrl)->assertOk()->assertSee('Renamed')->assertSee('The configuration was not saved.');
        $this->assertSame('AUS', $region->fresh()->name);
        $this->assertSame(['MEL', 'SYD'], $region->fresh()->offices->pluck('code')->all());
        $this->assertSame(3, Region::count());

        $this->from($formUrl)->patch('/configuration/regions/'.$region->id, [
            'name' => 'AUS', '_region_form' => '1',
        ])->assertSessionHasErrors('offices');
        $response = $this->get($formUrl)->assertOk();
        $this->assertDoesNotMatchRegularExpression('/data-office-code="[^"]+"\s+checked/', $response->getContent());
    }

    public function test_only_admins_can_view_create_or_update_regions(): void
    {
        $region = Region::firstOrFail();
        $this->get('/configuration?tab=regions')->assertRedirect('/login');
        $this->post('/configuration/regions', [])->assertRedirect('/login');
        $this->patch('/configuration/regions/'.$region->id, [])->assertRedirect('/login');
        foreach (['Counsellor', User::ACCOUNT_TYPE_MANAGER, User::ACCOUNT_TYPE_REGIONAL_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]));
            $this->get('/configuration?tab=regions')->assertForbidden();
            $this->post('/configuration/regions', [])->assertForbidden();
            $this->patch('/configuration/regions/'.$region->id, [])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['account_type' => 'Super Admin']))
            ->get('/configuration?tab=regions')->assertOk();
    }

    public function test_region_access_tracks_office_and_manager_changes_without_altering_direct_assignments(): void
    {
        $admin = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]);
        $manager = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_REGIONAL_MANAGER, 'offices' => ['JKT']]);
        $replacement = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_REGIONAL_MANAGER, 'offices' => []]);
        $region = Region::where('name', 'AUS')->firstOrFail();
        $this->actingAs($admin)->patch('/configuration/regions/'.$region->id, [
            'name' => 'AUS', 'offices' => $region->offices->modelKeys(), 'regional_manager_id' => $manager->id,
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['JKT', 'MEL', 'SYD'], $manager->accessibleOfficeCodes());
        $this->assertSame('JKT', $manager->defaultOfficeCode());
        $this->actingAs($manager)->get('/my-offices/clients?offices[]=BRA')->assertOk()
            ->assertViewHas('selectedOfficeCodes', fn ($codes) => count($codes) === 3 && ! in_array('BRA', $codes))
            ->assertSee('Ana Santos');
        $this->get('/leads')->assertOk()->assertViewHas('offices', fn ($offices) => count($offices) === 3 && isset($offices['MEL'], $offices['SYD'], $offices['JKT'])
        );

        $this->actingAs($admin)->patch('/configuration/regions/'.$region->id, [
            'name' => 'AUS', 'offices' => [Office::where('code', 'SYD')->value('id')], 'regional_manager_id' => $replacement->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['JKT'], $manager->accessibleOfficeCodes());
        $this->assertSame(['SYD'], $replacement->accessibleOfficeCodes());
        $this->actingAs($replacement)->get('/my-offices/clients')->assertOk()
            ->assertViewHas('availableOffices', ['SYD' => 'Sydney'])->assertDontSee('Ana Santos');
        $this->get('/leads')->assertOk()->assertViewHas('offices', ['SYD' => 'Sydney']);
        $replacement->update(['account_type' => User::ACCOUNT_TYPE_MANAGER]);
        $this->assertSame([], $replacement->accessibleOfficeCodes());
    }
}
