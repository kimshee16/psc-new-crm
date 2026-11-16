<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounsellorIntakeFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_name_is_optional_in_the_form_and_submission(): void
    {
        $counsellor = $this->counsellor();
        $html = $this->get(route('counsellor-intake.show', $counsellor))->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertFalse($xpath->query('//input[@name="surname"]')->item(0)->hasAttribute('required'));
        $this->assertTrue($xpath->query('//input[@name="dob"]')->item(0)->hasAttribute('required'));

        foreach ([[], ['surname' => ''], ['surname' => null]] as $index => $optional) {
            $payload = $this->payload(['email' => 'visitor'.$index.'@example.test']);
            unset($payload['surname']);
            $this->post(route('counsellor-intake.submit', $counsellor), $payload + $optional)
                ->assertSessionHasNoErrors()->assertRedirect(route('counsellor-intake.thanks', $counsellor));

            $client = Client::where('email', $payload['email'])->sole();
            $this->assertSame('', $client->surname);
            $this->assertSame('Sydney', $client->admin_office);
            $this->assertDatabaseHas('leads', [
                'client_id' => $client->id, 'surname' => '', 'source' => 'Counsellor intake',
                'prospective_service' => '', 'assigned_user_id' => $counsellor->id,
                'assigned_office_code' => 'SYD',
            ]);
        }
        $this->assertSame(3, Client::count());
    }

    public function test_optional_surname_still_validates_supplied_values(): void
    {
        $counsellor = $this->counsellor();
        foreach (['<invalid>', str_repeat('A', 81)] as $surname) {
            $this->post(route('counsellor-intake.submit', $counsellor), $this->payload(['surname' => $surname]))
                ->assertSessionHasErrors('surname');
        }
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_omitted_surname_links_a_unique_client_and_preserves_their_surname(): void
    {
        $counsellor = $this->counsellor();
        $url = route('counsellor-intake.submit', $counsellor);
        $this->post($url, $this->payload())->assertSessionHasNoErrors();
        $client = Client::sole();

        $this->post($url, $this->payload(['surname' => '', 'email' => 'VISITOR@EXAMPLE.TEST']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clients', 1);
        $this->assertSame('Visitor', $client->fresh()->surname);
        $this->assertSame($client->id, Lead::latest('id')->firstOrFail()->client_id);

        $this->post($url, $this->payload(['surname' => '', 'email' => 'another@example.test']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clients', 2);
        $this->assertNotSame($client->id, Lead::latest('id')->firstOrFail()->client_id);
    }

    public function test_ambiguous_partial_identity_does_not_overwrite_an_existing_client(): void
    {
        $counsellor = $this->counsellor();
        $url = route('counsellor-intake.submit', $counsellor);
        $this->post($url, $this->payload())->assertSessionHasNoErrors();
        $client = Client::sole();
        $duplicate = $client->replicate();
        $duplicate->client_id++;
        $duplicate->save();

        $this->post($url, $this->payload(['surname' => '']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clients', 3);
        $this->assertNotContains(Lead::latest('id')->firstOrFail()->client_id, [$client->id, $duplicate->id]);
        $this->assertSame('Visitor', $client->fresh()->surname);
    }

    public function test_lead_record_keeps_service_blank_until_staff_choose_one(): void
    {
        $counsellor = $this->counsellor();
        $this->post(route('counsellor-intake.submit', $counsellor), $this->payload())
            ->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $this->actingAs($counsellor)->get(route('leads.show', $lead->lead_id))->assertOk()
            ->assertViewHas('lead', fn ($record) => $record['prospective_service'] === '' && $record['source'] === 'Counsellor intake')
            ->assertSee('value="" selected>Select prospective service</option>', false);

        $payload = ['prospective_service' => '', 'status' => 'To be contacted', 'priority' => 'medium',
            'source' => 'Counsellor intake', 'assigned_name' => $counsellor->name];
        $this->patch(route('leads.update', $lead->lead_id), $payload)->assertSessionHasNoErrors();
        $this->assertSame('', $lead->fresh()->prospective_service);

        $this->patch(route('leads.update', $lead->lead_id), array_replace($payload, ['prospective_service' => 'Study']))
            ->assertSessionHasNoErrors();
        $this->get(route('leads.show', $lead->lead_id))->assertOk()
            ->assertViewHas('lead', fn ($record) => $record['prospective_service'] === 'Study');
        $this->assertSame('Study', $lead->fresh()->prospective_service);
    }

    public function test_legacy_intake_source_displays_and_saves_the_new_label(): void
    {
        $counsellor = $this->counsellor();
        $this->post(route('counsellor-intake.submit', $counsellor), $this->payload())->assertSessionHasNoErrors();
        $lead = Lead::sole();
        $lead->update(['source' => 'PSC-7 counsellor intake']);
        $this->actingAs($counsellor)->get(route('leads.show', $lead->lead_id))->assertOk()
            ->assertDontSee('PSC-7 counsellor intake')
            ->assertViewHas('lead', fn ($record) => $record['source'] === 'Counsellor intake');
        $this->patch(route('leads.update', $lead->lead_id), [
            'prospective_service' => '', 'status' => 'To be contacted', 'priority' => 'medium',
            'source' => 'Counsellor intake', 'assigned_name' => $counsellor->name,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Counsellor intake', $lead->fresh()->source);
    }

    private function counsellor(): User
    {
        return User::factory()->create(['name' => 'Priya Raman', 'account_type' => 'Counsellor', 'offices' => ['SYD']]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Walkin', 'surname' => 'Visitor', 'email' => 'visitor@example.test',
            'phone_country_code' => '+61', 'phone_number' => '412 555 999', 'dob' => '1999-05-20',
            'nationality' => 'Philippines', 'current_location' => 'Australia - Melbourne',
        ], $overrides);
    }
}
