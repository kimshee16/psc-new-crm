<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EventReferral;
use App\Models\Lead;
use App\Models\PscEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_referrals_are_available_in_configuration_and_registration(): void
    {
        $names = ['WhatsApp', 'Instagram', 'Facebook', 'Website', 'Word of Mouth', 'Celebgram Campaign'];
        $this->assertSame($names, EventReferral::activeOptions()->pluck('name')->all());
        $this->actingAs(User::factory()->create())
            ->get('/configuration?tab=event-marketing')->assertOk()
            ->assertSee('Event Referral')->assertSeeInOrder($names);
        $this->get($this->event()->registrationUrl())->assertOk()->assertSeeInOrder($names);
    }

    public function test_default_migration_preserves_existing_configuration_when_reapplied(): void
    {
        $whatsApp = EventReferral::where('name', 'WhatsApp')->firstOrFail();
        $whatsApp->update(['active' => false, 'sort_order' => 20]);
        EventReferral::create(['name' => 'Partner referral', 'active' => true, 'sort_order' => 7]);

        $migration = require database_path('migrations/2026_10_27_000002_seed_event_referral_options.php');
        $migration->up();

        $this->assertSame(7, EventReferral::count());
        $this->assertFalse($whatsApp->fresh()->active);
        $this->assertSame(20, $whatsApp->fresh()->sort_order);
        $this->assertDatabaseHas('event_referrals', ['name' => 'Partner referral', 'active' => true]);
    }

    public function test_admin_can_configure_options_and_only_active_options_appear_in_order(): void
    {
        EventReferral::query()->delete();
        $this->actingAs(User::factory()->create());
        $this->get('/configuration?tab=event-marketing')->assertOk()->assertSee('No event referral options configured.');
        $this->post('/configuration/event-marketing', ['name' => 'Friend', 'sort_order' => 2, 'active' => true])
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=event-marketing');
        $friend = EventReferral::sole();
        $this->post('/configuration/event-marketing', ['name' => 'Social media', 'sort_order' => 1, 'active' => true])
            ->assertSessionHasNoErrors();
        $event = $this->event();
        $this->get($event->registrationUrl())->assertOk()->assertSeeInOrder(['Social media', 'Friend'])
            ->assertSee('How did you hear about our event?')->assertSee('name="event_referral_id" required', false);
        $this->patch('/configuration/event-marketing/'.$friend->id, ['name' => 'Friend or family', 'sort_order' => 3, 'active' => false])
            ->assertSessionHasNoErrors();
        $this->get($event->registrationUrl())->assertDontSee('Friend or family');
        $this->get('/configuration?tab=event-marketing')->assertSee('Friend or family');
        $this->assertFalse($friend->fresh()->active);
        $this->assertSame(3, $friend->fresh()->sort_order);
    }

    public function test_configuration_requires_admin_and_valid_unique_options(): void
    {
        $option = EventReferral::create(['name' => 'Friend', 'active' => true]);
        $data = ['name' => 'Social media', 'sort_order' => 1, 'active' => true];
        $this->post('/configuration/event-marketing', $data)->assertRedirect('/login');
        $this->patch('/configuration/event-marketing/'.$option->id, $data)->assertRedirect('/login');
        foreach (['Counsellor', 'Manager', 'Regional manager'] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]));
            $this->get('/configuration?tab=event-marketing')->assertForbidden();
            $this->post('/configuration/event-marketing', $data)->assertForbidden();
            $this->patch('/configuration/event-marketing/'.$option->id, $data)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['account_type' => 'Super Admin']));
        $this->get('/configuration?tab=event-marketing')->assertOk();
        foreach ([['name', 'Friend'], ['name', '   '], ['name', str_repeat('a', 161)], ['sort_order', 0], ['sort_order', 1000], ['active', 'invalid']] as [$field, $value]) {
            $this->post('/configuration/event-marketing', array_replace($data, [$field => $value]))->assertSessionHasErrors($field);
        }
        EventReferral::create(['name' => 'Social media']);
        $this->patch('/configuration/event-marketing/'.$option->id, $data)->assertSessionHasErrors('name');
        $this->assertSame('Friend', $option->fresh()->name);
    }

    public function test_registration_requires_a_current_active_option_and_preserves_input_on_error(): void
    {
        $event = $this->event();
        $active = EventReferral::create(['name' => 'Social media', 'active' => true]);
        $inactive = EventReferral::create(['name' => 'Old campaign', 'active' => false]);
        foreach ([null, '', 999999, $inactive->id, 'Social media', [$active->id]] as $value) {
            $this->from($event->registrationUrl())->post($event->registrationUrl(), $this->registrationData() + ['event_referral_id' => $value])
                ->assertRedirect($event->registrationUrl())->assertSessionHasErrors('event_referral_id');
        }
        $this->assertSame(0, Lead::count());
        $this->assertSame(0, Client::count());
        $this->post($event->registrationUrl(), array_replace($this->registrationData(), ['email' => 'invalid', 'event_referral_id' => $active->id]))
            ->assertSessionHasErrors('email')->assertSessionDoesntHaveErrors('event_referral_id');
        $this->get($event->registrationUrl())->assertSee('value="'.$active->id.'" selected', false)->assertSee('Visitor');
    }

    public function test_lead_records_the_selected_label_and_retains_history_when_configuration_changes(): void
    {
        $admin = User::factory()->create(['offices' => ['MEL']]);
        $event = $this->event();
        $option = EventReferral::create(['name' => 'Friend', 'active' => true]);
        $this->post($event->registrationUrl(), $this->registrationData() + [
            'event_referral_id' => $option->id, 'event_discovery_channel' => 'Spoofed answer',
        ])->assertSessionHasNoErrors()->assertRedirect(route('event-registration.thanks', $event));
        $lead = Lead::sole();
        $this->assertSame('Friend', $lead->event_discovery_channel);
        $option->update(['name' => 'Renamed option', 'active' => false]);
        $this->actingAs($admin)->get('/leads/'.$lead->lead_id)->assertOk()
            ->assertSee('Event discovery channel')->assertSee('value="Friend"', false)
            ->assertSee('id="lead-event-discovery-field" class="lead-field "', false)->assertDontSee('Renamed option');
        $this->patch('/leads/'.$lead->lead_id, [
            'prospective_service' => 'Study', 'status' => 'Consulting', 'priority' => 'medium',
            'source' => 'Manual', 'assigned_name' => $admin->name, 'event_discovery_channel' => 'Tampered',
        ])->assertSessionHasNoErrors();
        $this->get('/leads/'.$lead->lead_id)->assertOk()
            ->assertSee('id="lead-event-discovery-field" class="lead-field hidden"', false);
        $this->assertSame('Friend', $lead->fresh()->event_discovery_channel);
        $lead->update(['source' => 'Event', 'event_discovery_channel' => null]);
        $this->get('/leads/'.$lead->lead_id)->assertOk()->assertSee('placeholder="Not recorded"', false);
    }

    public function test_no_active_options_disables_registration_and_rejects_submission(): void
    {
        EventReferral::query()->update(['active' => false]);
        $event = $this->event();
        $this->get($event->registrationUrl())->assertOk()->assertSee('Registration is temporarily unavailable.')
            ->assertSee('class="psc-event-submit" disabled', false);
        $this->post($event->registrationUrl(), $this->registrationData())->assertSessionHasErrors('event_referral_id');
        $this->assertSame(0, Lead::count());
    }

    private function event(): PscEvent
    {
        return PscEvent::create(['name' => 'Study Fair', 'starts_at' => '2026-11-18T10:00', 'location' => 'Melbourne', 'active' => true]);
    }

    private function registrationData(): array
    {
        return [
            'first_name' => 'Event', 'surname' => 'Visitor', 'email' => 'event.visitor@example.test',
            'phone_country_code' => '+61', 'phone_number' => '412 555 999', 'dob' => '1999-05-20',
            'nationality' => 'Philippines', 'current_location' => 'Australia - Melbourne',
        ];
    }
}
