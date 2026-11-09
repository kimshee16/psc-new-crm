<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Client;
use App\Models\NominationSubclass;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\NominationTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NominationTestDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_uat_data_is_repeatable_visible_and_usable_for_nomination_creation(): void
    {
        $owner = User::factory()->create(['email' => 'admin@psccrm.test']);
        $unrelated = Organisation::create(['name' => 'Existing organisation', 'created_by_user_id' => $owner->id]);
        $this->seed(NominationTestDataSeeder::class);
        $organisation = Organisation::where('name', '[UAT Nomination] No SBS Pty Ltd')->firstOrFail();
        $nomination = $organisation->applications()->where('category', 'Nomination')->firstOrFail();
        $nomination->update(['priority' => 'H']);
        NominationSubclass::where('subclass', '482')->update(['sbs_required' => false]);
        $this->seed(NominationTestDataSeeder::class);
        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseCount('organisations', 5);
        $this->assertDatabaseCount('applications', 11);
        $this->assertSame('H', $nomination->fresh()->priority);
        $this->assertFalse(NominationSubclass::where('subclass', '482')->firstOrFail()->sbs_required);
        $this->assertSame('Existing organisation', $unrelated->fresh()->name);
        $this->assertSame(0, $organisation->applications()->where('category', 'SBS')->count());
        $this->assertTrue(Application::where('category', 'SBS')->where('status', 'Active')->sole()->expiry_date->isFuture());
        $this->assertTrue(Application::where('category', 'SBS')->where('status', 'Expired')->sole()->expiry_date->isPast());
        $this->assertNull(Application::where('category', 'SBS')->where('status', 'Not Started')->sole()->expiry_date);

        $this->actingAs($owner);
        foreach (Organisation::where('name', 'like', '[UAT Nomination]%')->get() as $record) {
            $this->get(route('organisations.show', $record))->assertOk()->assertSee('Create a nomination');
            foreach ($record->applications()->where('category', 'Nomination')->get() as $example) {
                $this->get(route('applications.show', $example))->assertOk()->assertSee($example->nomination_pathway);
            }
        }
        foreach ([910074 => 'Ana Maria Santos', 910170 => 'Daniel Min Park'] as $id => $name) {
            $this->getJson(route('organisations.nominees', ['organisation' => $organisation, 'q' => (string) $id]))
                ->assertOk()->assertJsonPath('clients.0.client_id', $id)->assertJsonPath('clients.0.name', $name);
        }
        $this->post(route('organisations.applications.store', $organisation), [
            'category' => 'Nomination', 'nominee_client_reference' => 910074,
            'visa_subclass' => '482 - Skills in Demand (SID) Visa',
        ])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_a_conflicting_client_id_rolls_back_the_seed_without_overwriting_existing_data(): void
    {
        User::factory()->create(['email' => 'admin@psccrm.test']);
        Client::create(['client_id' => 910170, 'first_name' => 'Existing', 'surname' => 'Person',
            'email' => 'existing@example.test', 'mobile' => '', 'nationality' => '',
            'current_location' => '', 'admin_office' => '', 'primary_counsellor' => '']);
        try {
            $this->seed(NominationTestDataSeeder::class);
            $this->fail('A conflicting public Client ID must stop the seed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('910170 is already in use', $exception->getMessage());
        }
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseHas('clients', ['client_id' => 910170, 'first_name' => 'Existing', 'email' => 'existing@example.test']);
        $this->assertDatabaseCount('organisations', 0);
        $this->assertDatabaseCount('applications', 0);
    }
}
