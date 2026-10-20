<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Country;
use App\Models\LeadRedirection;
use App\Models\PscEvent;
use App\Models\User;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_defaults_and_legacy_nationalities_are_preserved(): void
    {
        $expected = WebsiteLeadReferences::defaultCountries();
        sort($expected);
        $this->assertSame($expected, WebsiteLeadReferences::countries());
        foreach (['Australian', 'Brazilian', 'Chinese', 'Indian', 'Indonesian', 'Nepalese', 'Philippine'] as $legacy) {
            $this->assertContains($legacy, Country::clientNationalities());
        }
        $this->assertSame('Australia +61', WebsiteLeadReferences::phoneCountryCodes()['+61']);
    }

    public function test_admin_can_search_create_rename_deactivate_reactivate_and_remove_a_country(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/configuration?tab=countries')->assertOk()->assertSee('Add Country')->assertSee('Search countries');
        $this->get('/configuration?tab=countries&action=create')->assertOk()->assertSee('Country Name');
        $this->post('/configuration/countries', ['name' => 'Test Country', 'active' => true])
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=countries');
        $country = Country::where('name', 'Test Country')->firstOrFail();
        $this->get('/configuration?tab=countries&search=Test')->assertOk()->assertSee('Test Country')->assertDontSee('Afghanistan');
        $this->get('/configuration?tab=countries&edit='.$country->id)->assertOk()->assertSee('Edit Country');
        $this->patch('/configuration/countries/'.$country->id, ['name' => 'Updated Country', 'active' => false])
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=countries');
        $this->assertDatabaseHas('countries', ['id' => $country->id, 'name' => 'Updated Country', 'active' => false]);
        $this->assertNotContains('Updated Country', WebsiteLeadReferences::countries());
        $this->patch('/configuration/countries/'.$country->id, ['name' => 'Updated Country', 'active' => true])->assertSessionHasNoErrors();
        $this->assertContains('Updated Country', WebsiteLeadReferences::countries());
        $this->delete('/configuration/countries/'.$country->id)->assertRedirect('/configuration?tab=countries');
        $this->assertDatabaseMissing('countries', ['id' => $country->id]);
    }

    public function test_validation_rejects_duplicates_invalid_values_and_preserves_failed_edit_input(): void
    {
        $this->actingAs(User::factory()->create());
        $country = Country::where('name', 'Australia')->firstOrFail();
        foreach (['', ['invalid'], str_repeat('a', 81), 'Philippines', ' philippines ', 'Philippine', 'philippine'] as $name) {
            $this->post('/configuration/countries', ['name' => $name, 'active' => true])->assertSessionHasErrors('name');
            $this->patch('/configuration/countries/'.$country->id, ['name' => $name, 'active' => true])->assertSessionHasErrors('name');
        }
        $editUrl = '/configuration?tab=countries&edit='.$country->id;
        $this->from($editUrl)->patch('/configuration/countries/'.$country->id, ['name' => 'Changed Country', 'active' => 'invalid'])
            ->assertRedirect($editUrl)->assertSessionHasErrors('active')->assertSessionHasInput('name', 'Changed Country');
        $this->get($editUrl)->assertOk()->assertSee('value="Changed Country"', false);
        $this->post('/configuration/countries', [])->assertSessionHasErrors(['name', 'active']);
        $this->assertSame('Australia', $country->fresh()->name);
    }

    public function test_only_administrators_can_maintain_countries(): void
    {
        $country = Country::where('name', 'Australia')->firstOrFail();
        $payload = ['name' => 'Changed Country', 'active' => true];
        $this->get('/configuration?tab=countries')->assertRedirect('/login');
        $this->post('/configuration/countries', $payload)->assertRedirect('/login');
        $this->patch('/configuration/countries/'.$country->id, $payload)->assertRedirect('/login');
        $this->delete('/configuration/countries/'.$country->id)->assertRedirect('/login');
        foreach (['Counsellor', User::ACCOUNT_TYPE_MANAGER, User::ACCOUNT_TYPE_REGIONAL_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]));
            $this->get('/configuration?tab=countries')->assertForbidden();
            $this->post('/configuration/countries', $payload)->assertForbidden();
            $this->patch('/configuration/countries/'.$country->id, $payload)->assertForbidden();
            $this->delete('/configuration/countries/'.$country->id)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['account_type' => 'Super Admin']))
            ->patch('/configuration/countries/'.$country->id, $payload)->assertSessionHasNoErrors();
    }

    public function test_configured_countries_feed_all_forms_and_disabled_countries_are_rejected(): void
    {
        $admin = User::factory()->create();
        $event = PscEvent::create(['name' => 'Study Event', 'starts_at' => now()->addDay(), 'location' => 'Melbourne', 'active' => true]);
        $this->actingAs($admin)->post('/configuration/countries', ['name' => 'Test Country', 'active' => true])->assertSessionHasNoErrors();
        $country = Country::where('name', 'Test Country')->firstOrFail();
        $urls = ['/contact', '/book-a-free-consultation', '/intake/'.$admin->id, '/events/'.$event->id.'/register'];
        foreach ($urls as $index => $url) {
            $this->get($url)->assertOk()->assertViewHas('countries', fn ($countries) => in_array('Test Country', $countries, true));
            $this->withSession(['psc.website_forms.captcha' => 'ABC123'])->post($url, $this->leadPayload('new'.$index.'@example.test'))
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('leads', ['email' => 'new'.$index.'@example.test', 'nationality' => 'Test Country']);
        }
        $this->get('/clients/create')->assertOk()->assertViewHas('nationalities', fn ($values) => in_array('Test Country', $values, true));
        $this->get('/configuration?tab=redirections&action=create')->assertOk()->assertSee('Test Country');
        $this->post('/configuration/redirections', $this->redirectionPayload('Test Country'))->assertSessionHasNoErrors();
        $rule = LeadRedirection::query()->sole();

        $this->patch('/configuration/countries/'.$country->id, ['name' => 'Test Country', 'active' => false])->assertSessionHasNoErrors();
        foreach ($urls as $index => $url) {
            $this->get($url)->assertOk()->assertViewHas('countries', fn ($countries) => ! in_array('Test Country', $countries, true));
            $this->withSession(['psc.website_forms.captcha' => 'ABC123'])->post($url, $this->leadPayload('disabled'.$index.'@example.test'))
                ->assertSessionHasErrors('nationality');
            $this->assertDatabaseMissing('leads', ['email' => 'disabled'.$index.'@example.test']);
        }
        $this->get('/clients/create')->assertOk()->assertViewHas('nationalities', fn ($values) => ! in_array('Test Country', $values, true));
        $this->post('/clients', $this->clientPayload('Test Country'))->assertSessionHasErrors('nationality');
        $this->post('/configuration/redirections', $this->redirectionPayload('Test Country'))->assertSessionHasErrors('nationality');
        $this->get('/configuration?tab=redirections&edit='.$rule->id)->assertOk()->assertSee('Test Country');
        $this->patch('/configuration/redirections/'.$rule->id, $this->redirectionPayload('Test Country'))
            ->assertSessionHasNoErrors();
    }

    public function test_renaming_keeps_clients_leads_and_routing_consistent_and_blocks_removing_used_countries(): void
    {
        $this->actingAs(User::factory()->create());
        $country = Country::where('name', 'Philippines')->firstOrFail();
        $this->post('/configuration/redirections', $this->redirectionPayload('Philippines'))->assertSessionHasNoErrors();
        $this->withSession(['psc.website_forms.captcha' => 'ABC123'])
            ->post('/contact', array_replace($this->leadPayload(), ['nationality' => 'Philippines']))->assertSessionHasNoErrors();
        $this->patch('/configuration/countries/'.$country->id, ['name' => 'Renamed Country', 'active' => true])->assertSessionHasNoErrors();
        foreach (['clients', 'leads', 'lead_redirections'] as $table) {
            $this->assertDatabaseHas($table, ['nationality' => 'Renamed Country']);
            $this->assertDatabaseMissing($table, ['nationality' => 'Philippines']);
        }
        $this->assertSame('SYD', app(WebsiteLeadReferences::class)->officeCodeForLead('Renamed Country', 'Australia - Melbourne'));
        $this->assertContains('Philippine', Country::clientNationalities());
        $this->delete('/configuration/countries/'.$country->id)->assertSessionHas('error');
        $this->assertDatabaseHas('countries', ['id' => $country->id]);
    }

    public function test_existing_client_can_keep_an_inactive_legacy_nationality_when_edited(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/clients', $this->clientPayload('Philippine'))->assertSessionHasNoErrors();
        $client = Client::where('email', 'manual@example.test')->firstOrFail();
        $country = Country::where('name', 'Philippines')->firstOrFail();
        $this->delete('/configuration/countries/'.$country->id)->assertSessionHas('error');
        $this->patch('/configuration/countries/'.$country->id, ['name' => 'Philippines', 'active' => false])->assertSessionHasNoErrors();
        $this->assertNotContains('Philippine', Country::clientNationalities());
        $this->get('/clients/'.$client->client_id)->assertOk()
            ->assertViewHas('nationalities', fn ($values) => in_array('Philippine', $values, true));
        $this->post('/clients/'.$client->client_id.'/edit')->assertRedirect();
        $this->patch('/clients/'.$client->client_id, array_replace($this->clientPayload('Philippine'), ['first_name' => 'Updated']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'first_name' => 'Updated', 'nationality' => 'Philippine']);
    }

    public function test_empty_or_all_inactive_country_lists_do_not_restore_defaults(): void
    {
        Country::query()->update(['active' => false]);
        $this->assertSame([], WebsiteLeadReferences::countries());
        $this->assertSame([], Country::clientNationalities());
        Country::query()->delete();
        $this->assertSame([], WebsiteLeadReferences::countries());
        $this->actingAs(User::factory()->create())->get('/configuration?tab=countries')->assertOk()->assertSee('No countries configured.');
        $this->assertDatabaseCount('countries', 0);
    }

    private function leadPayload(string $email = 'incoming@example.test'): array
    {
        return [
            'first_name' => 'Country', 'surname' => 'Test', 'dob' => '2000-01-01',
            'email' => $email, 'phone_country_code' => '+61', 'phone_number' => '412555333',
            'nationality' => 'Test Country', 'current_location' => 'Australia - Melbourne',
            'highest_education_level' => 'Bachelor Degree', 'captcha' => 'ABC123',
        ];
    }

    private function clientPayload(string $nationality): array
    {
        return array_replace($this->leadPayload('manual@example.test'), [
            'nationality' => $nationality, 'current_location' => 'Melbourne',
            'admin_office' => 'Melbourne', 'client_status' => 'Prospect',
        ]);
    }

    private function redirectionPayload(string $nationality): array
    {
        return ['nationality' => $nationality, 'current_location' => 'Australia - Melbourne', 'office_code' => 'SYD', 'active' => true];
    }
}
