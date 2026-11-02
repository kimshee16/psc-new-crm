<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PSC\VisaGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisaGrantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-30 09:00:00', 'UTC'));
    }

    private function application(User $user, array $attributes = []): Application
    {
        $client = Client::create([
            'client_id' => 7900 + Client::count(), 'first_name' => 'Grant', 'surname' => 'Client',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => $user->name,
            'current_visa' => 'Visitor visa', 'visa_expiry' => '2026-12-01',
        ]);

        return Application::create($attributes + [
            'client_id' => $client->id, 'category' => 'Visa', 'visa_subclass' => '500 - Student Visa',
            'primary_counsellor_id' => $user->id, 'migration_agent_id' => $user->id,
        ]);
    }

    private function fields(Application $application, array $changes = []): array
    {
        return $changes + [
            'status' => 'Granted', 'priority' => 'M',
            'visa_subclass' => $application->visa_subclass,
            'primary_counsellor_id' => $application->primary_counsellor_id,
            'migration_agent_id' => $application->migration_agent_id,
            'grant_effective_from' => '2026-10-30', 'grant_expires_on' => '2028-10-30',
        ];
    }

    public function test_granted_popup_is_only_available_for_visa_and_both_dates_are_required(): void
    {
        $user = User::factory()->create();
        $visa = $this->application($user);
        $this->actingAs($user)->get(route('applications.show', $visa))->assertOk()
            ->assertSee('Granted')->assertSee('visa-grant-dialog')->assertSee('Effective from')->assertSee('Expires on')
            ->assertSee('id="grant-visa-subclass" type="text" readonly', false)
            ->assertSee('id="grant-effective-from" type="date" required', false)
            ->assertSee('id="grant-expires-on" type="date" required', false);
        $study = $this->application($user, ['category' => 'Study']);
        $this->get(route('applications.show', $study))->assertOk()->assertDontSee('visa-grant-dialog')
            ->assertDontSee('value="Granted"', false);
    }

    public function test_save_updates_client_atomically_using_the_summary_subclass_and_retry_is_idempotent(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $fields = $this->fields($application, ['visa_subclass' => '590 - Student Guardian Visa']);
        $this->actingAs($user);
        for ($i = 0; $i < 2; $i++) {
            $this->patch(route('applications.update', $application), $fields)->assertSessionHasNoErrors()
                ->assertRedirect(route('applications.show', $application));
        }
        $this->assertSame('Granted', $application->fresh()->status);
        $this->assertDatabaseHas('clients', ['id' => $application->client_id,
            'current_visa' => '590 - Student Guardian Visa', 'visa_expiry' => '2028-10-30', 'first_name' => 'Grant']);
        $this->assertDatabaseCount('visa_grants', 1);
        $this->assertDatabaseHas('visa_grants', ['application_id' => $application->id,
            'visa_subclass' => '590 - Student Guardian Visa', 'effective_from' => '2026-10-30', 'expires_on' => '2028-10-30']);
        $this->assertNotNull(DB::table('visa_grants')->value('processed_at'));
        $this->get(route('clients.show', $application->client->client_id))->assertOk()
            ->assertSee('590 - Student Guardian Visa')->assertSee('2028-10-30');
    }

    public function test_future_grants_wait_until_effective_date_and_do_not_reapply_after_processing(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->actingAs($user)->patch(route('applications.update', $application), $this->fields($application, [
            'grant_effective_from' => '2026-11-01',
        ]))->assertSessionHasNoErrors();
        $this->artisan('visas:apply-grants')->assertSuccessful();
        $this->assertSame('Visitor visa', $application->client->fresh()->current_visa);
        $this->assertNull(DB::table('visa_grants')->value('processed_at'));
        $this->travelTo(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
        $this->artisan('visas:apply-grants')->assertSuccessful();
        $this->assertSame('500 - Student Visa', $application->client->fresh()->current_visa);
        $application->client->update(['current_visa' => 'Manual correction']);
        $this->artisan('visas:apply-grants')->assertSuccessful();
        $this->assertSame('Manual correction', $application->client->fresh()->current_visa);
    }

    public function test_client_view_catches_up_due_grants_without_waiting_for_scheduler(): void
    {
        $user = User::factory()->create();
        $longSubclass = '799 - '.rtrim(str_repeat('Custom subclass ', 10));
        $application = $this->application($user, ['visa_subclass' => $longSubclass]);
        $this->actingAs($user)->patch(route('applications.update', $application), $this->fields($application, [
            'grant_effective_from' => '2026-11-01',
        ]))->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00', 'UTC'));
        $this->get(route('clients.show', $application->client->client_id))->assertOk()->assertSee($longSubclass);
        $this->assertSame($longSubclass, $application->client->fresh()->current_visa);
    }

    public function test_invalid_dates_forged_subclass_and_missing_summary_fields_leave_both_records_unchanged(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->actingAs($user);
        foreach ([
            ['grant_effective_from' => null], ['grant_expires_on' => null],
            ['grant_effective_from' => '2026-02-30'], ['grant_expires_on' => '2026-01-01'],
            ['grant_effective_from' => ['invalid']], ['grant_expires_on' => ['invalid']],
            ['grant_visa_subclass' => 'Forged'], ['visa_subclass' => 'Unconfigured'], ['migration_agent_id' => null],
        ] as $changes) {
            $this->patchJson(route('applications.update', $application), $this->fields($application, $changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('visa_grants', 0);
        $this->assertSame('Not Started', $application->fresh()->status);
        $this->assertSame('Visitor visa', $application->client->fresh()->current_visa);
        $this->from(route('applications.show', $application))->patch(route('applications.update', $application),
            $this->fields($application, ['grant_expires_on' => null]))->assertSessionHasErrors('grant_expires_on');
        $this->get(route('applications.show', $application))->assertOk()->assertSee('data-has-errors="true"', false);
    }

    public function test_access_and_application_type_are_enforced(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->patch(route('applications.update', $application), $this->fields($application))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patchJson(route('applications.update', $application), $this->fields($application))->assertNotFound();
        $study = $this->application($user, ['category' => 'Study']);
        $this->actingAs($user)->patchJson(route('applications.update', $study), $this->fields($study))->assertUnprocessable();
        $organisation = $this->application($user, ['client_id' => null,
            'organisation_id' => Organisation::create(['name' => 'Organisation'])->id]);
        $this->patchJson(route('applications.update', $organisation), $this->fields($organisation))->assertUnprocessable();
        $this->assertDatabaseCount('visa_grants', 0);
    }

    public function test_leaving_granted_cancels_future_update_and_regrant_creates_a_new_snapshot(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        $this->actingAs($user)->patch(route('applications.update', $application), $this->fields($application, [
            'grant_effective_from' => '2026-11-01',
        ]))->assertSessionHasNoErrors();
        $fields = $this->fields($application, ['status' => 'In Progress']);
        unset($fields['grant_effective_from'], $fields['grant_expires_on']);
        $this->patch(route('applications.update', $application), $fields)->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00', 'UTC'));
        $this->assertSame(0, app(VisaGrants::class)->applyDue());
        $this->assertSame('Visitor visa', $application->client->fresh()->current_visa);
        $this->patch(route('applications.update', $application), $this->fields($application))->assertSessionHasNoErrors();
        $this->assertSame('500 - Student Visa', $application->client->fresh()->current_visa);
        $this->assertDatabaseCount('visa_grants', 2);
    }

    public function test_later_effective_grant_wins_over_backdated_and_overdue_grants(): void
    {
        $user = User::factory()->create();
        $first = $this->application($user);
        $second = $this->application($user, ['client_id' => $first->client_id, 'visa_subclass' => '590 - Student Guardian Visa']);
        $this->actingAs($user)->patch(route('applications.update', $first), $this->fields($first))->assertSessionHasNoErrors();
        $this->patch(route('applications.update', $second), $this->fields($second, [
            'grant_effective_from' => '2026-10-01',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('500 - Student Visa', $first->client->fresh()->current_visa);
        $this->assertSame(0, app(VisaGrants::class)->applyDue());
    }

    public function test_client_write_failure_rolls_back_grant_and_application_status(): void
    {
        $user = User::factory()->create();
        $application = $this->application($user);
        DB::unprepared("CREATE TRIGGER fail_visa_update BEFORE UPDATE OF current_visa ON clients BEGIN SELECT RAISE(ABORT, 'Simulated client failure'); END");
        $this->actingAs($user)->patchJson(route('applications.update', $application), $this->fields($application))->assertStatus(500);
        $this->assertSame('Not Started', $application->fresh()->status);
        $this->assertSame('Visitor visa', $application->client->fresh()->current_visa);
        $this->assertDatabaseCount('visa_grants', 0);
    }
}
