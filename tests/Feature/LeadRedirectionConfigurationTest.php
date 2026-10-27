<?php

namespace Tests\Feature;

use App\Models\LeadRedirection;
use App\Models\EventReferral;
use App\Models\Location;
use App\Models\PscEvent;
use App\Models\User;
use App\Support\PSC\LeadAssignmentService;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadRedirectionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_disable_and_remove_redirections(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/configuration?tab=redirections')->assertOk()
            ->assertSee('No redirections configured.')->assertSee('Add Redirection');
        $this->get('/configuration?tab=redirections&action=create')->assertOk()
            ->assertSee('Nationality')->assertSee('Current Location')->assertSee('Destination Office');

        $this->post('/configuration/redirections', $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=redirections');
        $rule = LeadRedirection::query()->sole();
        $this->assertDatabaseHas('lead_redirections', $this->payload());
        $this->get('/configuration?tab=redirections')->assertOk()->assertSee('Philippines')->assertSee('SYD - Sydney');
        $this->get('/configuration?tab=redirections&edit='.$rule->id)->assertOk()->assertSee('Edit Redirection');

        $this->patch('/configuration/redirections/'.$rule->id, $this->payload(['office_code' => 'MNL']))
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=redirections');
        $this->assertSame('MNL', $rule->fresh()->office_code);
        $this->assertSame('MNL', app(WebsiteLeadReferences::class)->officeCodeForLead('Philippines', 'Australia - Melbourne'));

        $this->patch('/configuration/redirections/'.$rule->id, $this->payload(['active' => false]))
            ->assertSessionHasNoErrors();
        $this->assertFalse($rule->fresh()->active);
        $this->assertSame('MEL', app(WebsiteLeadReferences::class)->officeCodeForLead('Philippines', 'Australia - Melbourne'));
        $this->patch('/configuration/redirections/'.$rule->id, $this->payload())->assertSessionHasNoErrors();
        $this->assertTrue($rule->fresh()->active);

        $this->delete('/configuration/redirections/'.$rule->id)->assertRedirect('/configuration?tab=redirections');
        $this->assertDatabaseMissing('lead_redirections', ['id' => $rule->id]);
        $this->assertSame('MEL', app(WebsiteLeadReferences::class)->officeCodeForLead('Philippines', 'Australia - Melbourne'));
    }

    public function test_duplicate_pairs_are_rejected_on_create_and_update_even_when_inactive(): void
    {
        $this->actingAs(User::factory()->create());
        $first = LeadRedirection::create($this->payload(['active' => false]));
        $second = LeadRedirection::create($this->payload(['nationality' => 'Australia']));

        $this->post('/configuration/redirections', $this->payload())->assertSessionHasErrors('nationality');
        $this->patch('/configuration/redirections/'.$second->id, $this->payload())->assertSessionHasErrors('nationality');
        $this->assertSame('Australia', $second->fresh()->nationality);
        $this->patch('/configuration/redirections/'.$first->id, $this->payload())->assertSessionHasNoErrors();
        $this->post('/configuration/redirections', $this->payload(['current_location' => 'Other Location']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('lead_redirections', 3);
    }

    public function test_invalid_options_are_rejected_and_failed_edits_keep_input(): void
    {
        $this->actingAs(User::factory()->create());
        $rule = LeadRedirection::create($this->payload());
        $editUrl = '/configuration?tab=redirections&edit='.$rule->id;
        foreach (['nationality' => 'Atlantis', 'current_location' => 'Missing location', 'office_code' => 'UNKNOWN', 'active' => 'invalid'] as $field => $value) {
            $this->post('/configuration/redirections', $this->payload([$field => $value]))->assertSessionHasErrors($field);
            $this->from($editUrl)->patch('/configuration/redirections/'.$rule->id, $this->payload([$field => $value]))
                ->assertRedirect($editUrl)->assertSessionHasErrors($field)->assertSessionHasInput($field, $value);
        }
        $this->post('/configuration/redirections', [])->assertSessionHasErrors(['nationality', 'current_location', 'office_code', 'active']);
        $this->assertDatabaseHas('lead_redirections', $this->payload());
        $this->assertDatabaseCount('lead_redirections', 1);
    }

    public function test_rule_options_use_the_same_locations_as_incoming_forms(): void
    {
        $this->actingAs(User::factory()->create());
        Location::create(['name' => 'New Location', 'office_code' => 'MEL', 'active' => true, 'sort_order' => 90]);
        Location::create(['name' => 'Inactive Location', 'office_code' => 'MEL', 'active' => false, 'sort_order' => 91]);
        $this->get('/configuration?tab=redirections&action=create')->assertOk()
            ->assertSee('New Location')->assertDontSee('Inactive Location');
        $this->post('/configuration/redirections', $this->payload(['current_location' => 'New Location']))->assertSessionHasNoErrors();
        $this->post('/configuration/redirections', $this->payload(['current_location' => 'Inactive Location']))->assertSessionHasErrors('current_location');
    }

    public function test_only_administrators_can_manage_redirections(): void
    {
        $rule = LeadRedirection::create($this->payload());
        $this->get('/configuration?tab=redirections')->assertRedirect('/login');
        $this->post('/configuration/redirections', $this->payload())->assertRedirect('/login');
        $this->patch('/configuration/redirections/'.$rule->id, $this->payload())->assertRedirect('/login');
        $this->delete('/configuration/redirections/'.$rule->id)->assertRedirect('/login');
        foreach (['Counsellor', User::ACCOUNT_TYPE_MANAGER, User::ACCOUNT_TYPE_REGIONAL_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]));
            $this->get('/configuration?tab=redirections')->assertForbidden();
            $this->post('/configuration/redirections', $this->payload())->assertForbidden();
            $this->patch('/configuration/redirections/'.$rule->id, $this->payload())->assertForbidden();
            $this->delete('/configuration/redirections/'.$rule->id)->assertForbidden();
        }
        $this->assertDatabaseHas('lead_redirections', $this->payload());
        $this->actingAs(User::factory()->create(['account_type' => 'Super Admin']))
            ->patch('/configuration/redirections/'.$rule->id, $this->payload(['office_code' => 'MNL']))
            ->assertSessionHasNoErrors();
    }

    public function test_routing_requires_both_values_and_overrides_even_the_other_location_default(): void
    {
        LeadRedirection::create($this->payload());
        LeadRedirection::create($this->payload(['current_location' => 'Other Location']));
        $service = app(LeadAssignmentService::class);
        $this->assertSame('SYD', $service->assign('Philippines', 'Australia - Melbourne')['office_code']);
        $this->assertSame('MEL', $service->assign('Australia', 'Australia - Melbourne')['office_code']);
        $this->assertSame('JKT', $service->assign('Philippines', 'Indonesia - Jakarta')['office_code']);
        $queue = $service->assign('Philippines', 'Other Location');
        $this->assertSame('SYD', $queue['office_code']);
        $this->assertNull($queue['user']);
        $this->assertSame('Sydney Web Queue', $queue['name']);
        $this->assertSame('MEL', $service->assign('Australia', 'Other Location')['office_code']);
    }

    public function test_stale_office_in_an_existing_rule_falls_back_to_the_location_office(): void
    {
        LeadRedirection::create($this->payload(['current_location' => 'Indonesia - Jakarta', 'office_code' => 'MISSING']));
        $this->assertSame('JKT', app(WebsiteLeadReferences::class)->officeCodeForLead('Philippines', 'Indonesia - Jakarta'));
        $assignment = app(LeadAssignmentService::class)->assign('Philippines', 'Indonesia - Jakarta');
        $this->assertSame('JKT', $assignment['office_code']);
        $this->assertStringNotContainsString('redirected', $assignment['reason']);
    }

    public function test_configured_rule_routes_incoming_contact_consultation_and_event_leads(): void
    {
        $admin = User::factory()->create(['include_in_round_robin' => false]);
        $counsellor = User::factory()->create(['offices' => ['SYD'], 'default_office_code' => 'SYD']);
        $this->actingAs($admin)->post('/configuration/redirections', $this->payload())->assertSessionHasNoErrors();
        $event = PscEvent::create(['name' => 'Study Event', 'starts_at' => now()->addDay(), 'location' => 'Melbourne', 'active' => true]);
        $this->app['auth']->forgetGuards();

        foreach (['/contact', '/book-a-free-consultation', '/events/'.$event->id.'/register'] as $index => $url) {
            $email = 'redirected'.$index.'@example.test';
            $this->withSession(['psc.website_forms.captcha' => 'ABC123'])->post($url, [
                'event_referral_id' => EventReferral::firstOrCreate(['name' => 'Social media'], ['active' => true])->id,
                'first_name' => 'Test', 'surname' => 'Lead', 'dob' => '2000-01-01',
                'email' => $email, 'phone_country_code' => '+61', 'phone_number' => '412555333',
                'nationality' => 'Philippines', 'current_location' => 'Australia - Melbourne',
                'highest_education_level' => 'Bachelor Degree', 'captcha' => 'ABC123',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('leads', [
                'email' => $email, 'assigned_office_code' => 'SYD', 'assigned_user_id' => $counsellor->id,
            ]);
            $this->assertDatabaseHas('clients', [
                'email' => $email, 'admin_office' => 'Sydney', 'primary_counsellor' => $counsellor->name,
            ]);
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'nationality' => 'Philippines', 'current_location' => 'Australia - Melbourne',
            'office_code' => 'SYD', 'active' => true,
        ], $overrides);
    }
}
