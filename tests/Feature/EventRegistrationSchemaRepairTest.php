<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EventReferral;
use App\Models\Lead;
use App\Models\PscEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventRegistrationSchemaRepairTest extends TestCase
{
    use RefreshDatabase;

    private const REPAIR = '2026_11_10_000002_ensure_event_discovery_channel_on_leads';

    public function test_forward_migration_repairs_reported_schema_and_restores_registration(): void
    {
        $event = $this->event();
        $option = EventReferral::where('name', 'WhatsApp')->firstOrFail();
        $option->update(['sort_order' => 25]);
        $this->assertDatabaseHas('migrations', ['migration' => '2026_10_27_000001_add_event_referrals']);

        // Reproduce the deployed database: referrals exist, but the lead column is missing.
        Schema::dropColumns('leads', 'event_discovery_channel');
        $this->post($event->registrationUrl(), $this->payload())->assertServerError();
        $this->assertSame(0, Client::count());
        $this->assertSame(0, Lead::count());

        DB::table('migrations')->where('migration', self::REPAIR)->delete();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/'.self::REPAIR.'.php', '--force' => true,
        ])->assertSuccessful();

        $this->post($event->registrationUrl(), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect(route('event-registration.thanks', $event));
        $lead = Lead::sole();
        $this->assertSame(Client::sole()->id, $lead->client_id);
        $this->assertSame($event->id, $lead->psc_event_id);
        $this->assertSame('Event', $lead->source);
        $this->assertSame('WhatsApp', $lead->event_discovery_channel);
        $this->assertSame(25, $option->fresh()->sort_order);
    }

    public function test_repair_and_original_migration_can_be_reapplied_without_losing_answers_or_options(): void
    {
        $event = $this->event();
        $this->post($event->registrationUrl(), $this->payload())->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $custom = EventReferral::create(['name' => 'Custom campaign', 'active' => false, 'sort_order' => 30]);
        $repair = require database_path('migrations/'.self::REPAIR.'.php');
        $original = require database_path('migrations/2026_10_27_000001_add_event_referrals.php');

        $repair->up();
        $repair->up();
        $original->up();
        $repair->down();

        $this->assertSame('WhatsApp', $lead->fresh()->event_discovery_channel);
        $this->assertSame(1, Client::count());
        $this->assertSame(1, Lead::count());
        $this->assertFalse($custom->fresh()->active);
        $this->assertSame(30, $custom->fresh()->sort_order);
    }

    public function test_original_migration_recovers_when_referral_table_exists_but_column_is_missing(): void
    {
        $custom = EventReferral::create(['name' => 'Retained campaign', 'active' => false]);
        Schema::dropColumns('leads', 'event_discovery_channel');
        $migration = require database_path('migrations/2026_10_27_000001_add_event_referrals.php');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('leads', 'event_discovery_channel'));
        $this->assertFalse($custom->fresh()->active);
        $this->post($this->event()->registrationUrl(), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('WhatsApp', Lead::sole()->event_discovery_channel);
    }

    private function event(): PscEvent
    {
        return PscEvent::create(['name' => 'Registration repair test', 'starts_at' => now()->addDay(),
            'location' => 'Melbourne', 'active' => true]);
    }

    private function payload(): array
    {
        return [
            'first_name' => 'Test', 'surname' => 'Visitor', 'email' => 'schema.repair@example.test',
            'phone_country_code' => '+61', 'phone_number' => '412 555 999', 'dob' => '1999-05-20',
            'nationality' => 'Philippines', 'current_location' => 'Australia - Melbourne',
            'event_referral_id' => EventReferral::where('name', 'WhatsApp')->firstOrFail()->id,
        ];
    }
}
