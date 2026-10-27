<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EventReferral;
use App\Models\Lead;
use App\Models\PscEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRegistrationFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_fields_are_not_required_by_the_browser(): void
    {
        $html = $this->get($this->event()->registrationUrl())->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        foreach (['surname', 'dob'] as $name) {
            $input = $xpath->query('//input[@name="'.$name.'"]')->item(0);
            $this->assertNotNull($input);
            $this->assertFalse($input->hasAttribute('required'));
        }
    }

    public function test_registration_accepts_missing_and_blank_optional_fields(): void
    {
        $event = $this->event();
        foreach ([[], ['surname' => '', 'dob' => ''], ['surname' => null, 'dob' => null]] as $index => $optional) {
            $payload = $this->payload(['email' => 'visitor'.$index.'@example.test']);
            unset($payload['surname'], $payload['dob']);
            $this->post($event->registrationUrl(), $payload + $optional)
                ->assertSessionHasNoErrors()->assertRedirect(route('event-registration.thanks', $event));

            $client = Client::where('email', $payload['email'])->sole();
            $this->assertSame('', $client->surname);
            $this->assertNull($client->dob);
            $this->assertDatabaseHas('leads', [
                'client_id' => $client->id, 'source' => 'Event', 'psc_event_id' => $event->id,
                'surname' => '', 'dob' => null, 'event_discovery_channel' => 'WhatsApp',
            ]);
        }
        $this->assertSame(3, Client::count());
    }

    public function test_partial_identity_links_unique_client_and_preserves_omitted_details(): void
    {
        $event = $this->event();
        foreach ([['dob'], ['surname'], ['surname', 'dob']] as $index => $omitted) {
            $payload = $this->payload(['first_name' => 'Event '.$index, 'email' => 'existing'.$index.'@example.test', 'middle_name' => 'Known']);
            $this->post($event->registrationUrl(), $payload)->assertSessionHasNoErrors();
            $client = Client::where('email', $payload['email'])->sole();
            foreach ($omitted as $field) {
                $payload[$field] = '';
            }
            $payload['middle_name'] = '';
            $payload['email'] = strtoupper($payload['email']);
            $this->post($event->registrationUrl(), $payload)->assertSessionHasNoErrors();

            $this->assertSame($client->id, Lead::latest('id')->firstOrFail()->client_id);
            $this->assertSame('Visitor', $client->fresh()->surname);
            $this->assertSame('Known', $client->fresh()->middle_name);
            $this->assertSame('1999-05-20', $client->fresh()->dob->toDateString());
        }
        $this->assertSame(3, Client::count());
        $this->assertSame(6, Lead::count());
    }

    public function test_partial_identity_does_not_match_by_name_alone_or_conflicting_details(): void
    {
        $event = $this->event();
        $this->post($event->registrationUrl(), $this->payload())->assertSessionHasNoErrors();
        $original = Client::sole();

        foreach ([
            ['dob' => '', 'email' => 'different@example.test'],
            ['dob' => '', 'surname' => 'Different'],
            ['surname' => '', 'dob' => '2001-01-01'],
        ] as $changes) {
            $this->post($event->registrationUrl(), $this->payload($changes))->assertSessionHasNoErrors();
            $this->assertNotSame($original->id, Lead::latest('id')->firstOrFail()->client_id);
        }
        $this->assertSame(4, Client::count());
        $this->assertSame('Visitor', $original->fresh()->surname);
        $this->assertSame('1999-05-20', $original->fresh()->dob->toDateString());
    }

    public function test_ambiguous_partial_identity_creates_a_new_client(): void
    {
        $event = $this->event();
        $this->post($event->registrationUrl(), $this->payload())->assertSessionHasNoErrors();
        $original = Client::sole();
        $duplicate = $original->replicate();
        $duplicate->client_id = $original->client_id + 1;
        $duplicate->save();

        $this->post($event->registrationUrl(), $this->payload(['surname' => '', 'dob' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame(3, Client::count());
        $this->assertNotContains(Lead::latest('id')->firstOrFail()->client_id, [$original->id, $duplicate->id]);
    }

    public function test_supplied_optional_fields_still_require_valid_values(): void
    {
        $event = $this->event();
        foreach ([['surname', '<invalid>'], ['dob', 'invalid'], ['dob', now()->addDay()->toDateString()]] as [$field, $value]) {
            $this->post($event->registrationUrl(), $this->payload([$field => $value]))
                ->assertSessionHasErrors($field);
        }
        $this->assertSame(0, Client::count());
        $this->assertSame(0, Lead::count());
    }

    private function event(): PscEvent
    {
        return PscEvent::create(['name' => 'Study Fair', 'starts_at' => now()->addDay(), 'location' => 'Melbourne', 'active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Event', 'surname' => 'Visitor', 'email' => 'visitor@example.test',
            'phone_country_code' => '+61', 'phone_number' => '412 555 999', 'dob' => '1999-05-20',
            'nationality' => 'Philippines', 'current_location' => 'Australia - Melbourne',
            'event_referral_id' => EventReferral::where('name', 'WhatsApp')->firstOrFail()->id,
        ], $overrides);
    }
}
