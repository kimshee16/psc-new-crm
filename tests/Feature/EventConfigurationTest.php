<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\PscEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_list_events_with_stable_ids_and_registration_links(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/configuration?tab=events')->assertOk()
            ->assertSee('PSC Events')->assertSee('WIP')->assertSee('No PSC events configured.');
        $this->get('/configuration?tab=events&action=create')->assertOk()
            ->assertSee('Assigned automatically when saved')->assertSee('Save Event');
        $this->post('/configuration/events', $this->eventData() + ['id' => 999, 'registration_url' => 'https://example.test'])
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=events');
        $event = PscEvent::sole();
        $url = $event->registrationUrl();
        $this->assertNotSame(999, $event->id);
        $this->assertSame(route('event-registration.show', $event), $url);

        $this->get('/configuration?tab=events')->assertOk()
            ->assertSee('Event ID '.$event->id)->assertSee($url)->assertSee('Open registration form');
        $this->get('/configuration?tab=events&edit='.$event->id)->assertOk()
            ->assertSee('Edit PSC Event')->assertSee('2026-11-18T10:00')->assertSee($url);
        $this->patch('/configuration/events/'.$event->id, $this->eventData([
            'name' => 'Renamed Education Fair', 'starts_at' => '2026-12-01T14:30',
            'location' => 'Sydney Convention Centre', 'id' => 999,
        ]))->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=events');

        $event->refresh();
        $this->assertSame($url, $event->registrationUrl());
        $this->assertSame('2026-12-01 14:30', $event->starts_at->format('Y-m-d H:i'));
        $this->get($url)->assertOk()->assertSee('Renamed Education Fair')
            ->assertSee('Sydney Convention Centre')->assertSee('1 December 2026')->assertSee('UTC');
        $this->assertSame(1, PscEvent::count());
        $this->get('/configuration?tab=events&edit=999999')->assertNotFound();
    }

    public function test_only_administrators_can_configure_events(): void
    {
        $event = PscEvent::create($this->eventData());
        $this->get('/configuration?tab=events')->assertRedirect('/login');
        $this->post('/configuration/events', $this->eventData())->assertRedirect('/login');
        $this->patch('/configuration/events/'.$event->id, $this->eventData())->assertRedirect('/login');
        foreach (['Counsellor', 'Manager', 'Regional manager'] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]));
            $this->get('/configuration?tab=events')->assertForbidden();
            $this->post('/configuration/events', $this->eventData())->assertForbidden();
            $this->patch('/configuration/events/'.$event->id, $this->eventData())->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['account_type' => 'Super Admin']))
            ->get('/configuration?tab=events')->assertOk();
        $this->assertSame(1, PscEvent::count());
    }

    public function test_invalid_event_details_preserve_existing_values_and_form_input(): void
    {
        $event = PscEvent::create($this->eventData());
        $this->actingAs(User::factory()->create());
        $editUrl = '/configuration?tab=events&edit='.$event->id;
        foreach ([
            ['name', '   '], ['name', str_repeat('a', 161)],
            ['starts_at', ''], ['starts_at', '2026-02-30T10:00'], ['starts_at', 'tomorrow'],
            ['location', ''], ['location', str_repeat('a', 181)], ['active', 'invalid'],
        ] as [$field, $value]) {
            $data = $this->eventData([$field => $value]);
            $this->from('/configuration?tab=events&action=create')->post('/configuration/events', $data)
                ->assertSessionHasErrors($field);
            $this->from($editUrl)->patch('/configuration/events/'.$event->id, $data)
                ->assertRedirect($editUrl)->assertSessionHasErrors($field);
        }
        $this->get($editUrl)->assertOk()->assertSee('The configuration was not saved.')
            ->assertSee('Study Pathways Fair');
        $this->assertSame(1, PscEvent::count());
        $this->assertSame('Study Pathways Fair', $event->fresh()->name);
        $this->assertTrue($event->fresh()->active);
    }

    public function test_configured_registration_tracks_event_and_deactivation_preserves_history(): void
    {
        $admin = User::factory()->create(['offices' => ['MEL']]);
        $this->actingAs($admin)->post('/configuration/events', $this->eventData())->assertSessionHasNoErrors();
        $event = PscEvent::sole();
        $other = PscEvent::create($this->eventData(['name' => 'Different Event']));
        $this->post($event->registrationUrl(), $this->registrationData() + [
            'psc_event_id' => $other->id, 'event_name' => 'Spoofed', 'source' => 'Manual',
        ])->assertSessionHasNoErrors()->assertRedirect(route('event-registration.thanks', $event));
        $lead = Lead::sole();
        $this->assertSame('Event', $lead->source);
        $this->assertSame($event->id, $lead->psc_event_id);
        $this->assertSame($event->name, $lead->event_name);
        $this->assertSame($event->id, $lead->event->id);
        $this->assertSame([$lead->id], $event->leads->modelKeys());

        $this->patch('/configuration/events/'.$event->id, $this->eventData([
            'name' => 'Updated Fair', 'active' => false,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Study Pathways Fair', $lead->fresh()->event_name);
        $this->assertSame($event->id, $lead->fresh()->psc_event_id);
        $this->get($event->registrationUrl())->assertNotFound();
        $this->post($event->registrationUrl(), $this->registrationData())->assertNotFound();
        $this->get(route('event-registration.thanks', $event))->assertNotFound();
        $this->assertSame(1, Lead::count());
        $this->get('/configuration?tab=events')->assertOk()->assertSee('Registration closed');

        $this->patch('/configuration/events/'.$event->id, $this->eventData(['name' => 'Updated Fair']))
            ->assertSessionHasNoErrors();
        $this->get($event->registrationUrl())->assertOk()->assertSee('Updated Fair');
        $this->post($event->registrationUrl(), $this->registrationData())->assertSessionHasNoErrors();
        $this->assertSame('Updated Fair', Lead::latest('id')->first()->event_name);
    }

    public function test_lead_selection_uses_ids_and_preserves_snapshot_when_event_is_renamed_or_inactive(): void
    {
        $admin = User::factory()->create(['offices' => ['MEL']]);
        $event = PscEvent::create($this->eventData());
        $second = PscEvent::create($this->eventData(['location' => 'Sydney']));
        $inactive = PscEvent::create($this->eventData(['name' => 'Closed Fair', 'active' => false]));
        $this->post($event->registrationUrl(), $this->registrationData())->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $this->actingAs($admin)->get('/leads/'.$lead->lead_id)->assertOk()
            ->assertSee('name="psc_event_id"', false)
            ->assertSee('#'.$event->id)->assertSee('#'.$second->id)->assertDontSee('Closed Fair');
        $data = $this->leadData($admin, ['psc_event_id' => $second->id, 'event_name' => 'Untrusted name']);
        $this->patch('/leads/'.$lead->lead_id, $data)->assertSessionHasNoErrors();
        $this->assertSame($second->id, $lead->fresh()->psc_event_id);
        $this->assertSame($second->name, $lead->fresh()->event_name);
        $second->update(['name' => 'Renamed Selected Fair', 'active' => false]);
        $this->get('/leads/'.$lead->lead_id)->assertOk()->assertSee('Renamed Selected Fair')
            ->assertSee('(Inactive)')->assertSee('Recorded event: Study Pathways Fair');
        $this->patch('/leads/'.$lead->lead_id, $data)->assertSessionHasNoErrors();
        $this->assertSame('Study Pathways Fair', $lead->fresh()->event_name);

        foreach ([$inactive->id, 999999] as $id) {
            $this->patch('/leads/'.$lead->lead_id, $this->leadData($admin, ['psc_event_id' => $id]))
                ->assertSessionHasErrors('psc_event_id');
        }
        $this->assertSame($second->id, $lead->fresh()->psc_event_id);
        $this->patch('/leads/'.$lead->lead_id, $this->leadData($admin, ['psc_event_id' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($lead->fresh()->psc_event_id);
        $this->assertSame('', $lead->fresh()->event_name);

        $this->patch('/leads/'.$lead->lead_id, $this->leadData($admin, ['psc_event_id' => $event->id]))
            ->assertSessionHasNoErrors();
        $this->patch('/leads/'.$lead->lead_id, $this->leadData($admin, ['source' => 'Manual', 'psc_event_id' => $event->id]))
            ->assertSessionHasNoErrors();
        $this->assertNull($lead->fresh()->psc_event_id);
        $this->assertSame('', $lead->fresh()->event_name);
    }

    public function test_legacy_lead_names_are_preserved_and_ambiguous_name_only_selections_are_rejected(): void
    {
        $admin = User::factory()->create(['offices' => ['MEL']]);
        $event = PscEvent::create($this->eventData());
        $this->post($event->registrationUrl(), $this->registrationData())->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $lead->update(['psc_event_id' => null, 'event_name' => 'Historical Expo']);
        $this->actingAs($admin)->get('/leads/'.$lead->lead_id)->assertOk()
            ->assertSee('Keep recorded event: Historical Expo');
        $this->patch('/leads/'.$lead->lead_id, $this->leadData($admin, ['psc_event_id' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Historical Expo', $lead->fresh()->event_name);
        PscEvent::create($this->eventData());
        $this->patch('/leads/'.$lead->lead_id, $this->leadData($admin, ['event_name' => $event->name]))
            ->assertSessionHasErrors('event_name');
        $this->assertNull($lead->fresh()->psc_event_id);
        $this->patch('/leads/'.$lead->lead_id, $this->leadData($admin, ['psc_event_id' => $event->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($event->id, $lead->fresh()->psc_event_id);
    }

    public function test_event_list_is_paginated_and_links_keep_the_events_tab(): void
    {
        for ($i = 0; $i < 21; $i++) {
            PscEvent::create($this->eventData(['name' => 'Event '.$i]));
        }
        $this->actingAs(User::factory()->create())->get('/configuration?tab=events')->assertOk()
            ->assertViewHas('events', fn ($events) => $events->total() === 21 && $events->count() === 20)
            ->assertSee('tab=events');
        $this->get('/configuration?tab=events&event_page=2')->assertOk()
            ->assertViewHas('events', fn ($events) => $events->count() === 1);
    }

    private function eventData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Study Pathways Fair', 'starts_at' => '2026-11-18T10:00',
            'location' => 'Melbourne Convention Centre', 'active' => true,
        ], $overrides);
    }

    private function registrationData(): array
    {
        return [
            'first_name' => 'Event', 'surname' => 'Visitor', 'email' => 'event.visitor@example.test',
            'phone_country_code' => '+61', 'phone_number' => '412 555 999', 'dob' => '1999-05-20',
            'nationality' => 'Philippines', 'current_location' => 'Australia - Melbourne',
        ];
    }

    private function leadData(User $user, array $overrides = []): array
    {
        return array_replace([
            'prospective_service' => 'Study', 'status' => 'Consulting', 'priority' => 'medium',
            'source' => 'Event', 'assigned_name' => $user->name,
        ], $overrides);
    }
}
