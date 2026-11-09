<?php

namespace Tests\Feature;

use App\Models\ApplicationWorkflow;
use App\Models\NominationSubclass;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PSC\ApplicationSummary;
use App\Support\PSC\NominationCreation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NominationSubclassConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_defaults_and_workflow_names_drive_the_dropdown_and_validation(): void
    {
        $this->assertSame(['482', '186', '494', '491'], NominationSubclass::orderBy('id')->pluck('subclass')->all());
        $this->assertSame(['482', '494'], NominationSubclass::where('sbs_required', true)->orderBy('id')->pluck('subclass')->all());
        ApplicationWorkflow::create(['name' => 'Employer visas', 'category' => 'Visa', 'summary_options' => [
            'visa_subclass' => ['482 - Configured employer visa', '491 - Configured regional visa'],
        ]]);
        $user = User::factory()->create();
        $organisation = Organisation::create(['name' => 'Sponsor', 'created_by_user_id' => $user->id]);
        $options = app(NominationCreation::class)->subclasses();
        $this->assertSame(['482 - Configured employer visa', '186 - Employer Nomination Scheme Visa',
            '494 - Skilled Employer Sponsored Regional Visa', '491 - Configured regional visa'], $options);
        $response = $this->actingAs($user)->get(route('organisations.show', $organisation))->assertOk();
        $response->assertViewHas('nominationSubclasses', $options);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $select = $xpath->query('//select[@id="nomination-visa-subclass"]')->item(0);
        $this->assertTrue($select->hasAttribute('required'));
        $this->assertSame('', $select->getElementsByTagName('option')->item(0)->getAttribute('value'));
        $this->assertCount(5, $select->getElementsByTagName('option'));
        $this->assertCount(0, $xpath->query('//select[@id="nomination-visa-subclass"]/option[@selected]'));
        $payload = ['category' => 'Nomination', 'external_nominee' => '1', 'nominee_name' => 'Nominee'];
        foreach (['500 - Student Visa', 'DAMA - Designated Area Migration Agreement Visa', '482 - Skills in Demand (SID) Visa'] as $invalid) {
            $this->postJson(route('organisations.applications.store', $organisation), $payload + ['visa_subclass' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('visa_subclass');
        }
        foreach ($options as $option) {
            $this->post(route('organisations.applications.store', $organisation), $payload + ['visa_subclass' => $option])
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('applications', 4);
    }

    public function test_admin_can_add_edit_remove_options_and_existing_nominations_keep_their_pathway(): void
    {
        $user = User::factory()->create();
        $organisation = Organisation::create(['name' => 'Sponsor', 'created_by_user_id' => $user->id]);
        $application = $organisation->applications()->create(['category' => 'Nomination', 'nominee_name' => 'Existing nominee',
            'nomination_pathway' => '186 - Employer Nomination Scheme Visa', 'primary_counsellor_id' => $user->id]);
        $this->actingAs($user)->get(route('configuration', ['tab' => 'nomination-subclasses']))
            ->assertOk()->assertSee('Add Subclass')->assertSee('SBS required');
        $this->get(route('configuration', ['tab' => 'nomination-subclasses', 'action' => 'create']))->assertOk()->assertSee('Save Subclass');
        $this->post(route('configuration.nomination-subclasses.store'), ['subclass' => '407', 'sbs_required' => true])->assertSessionHasNoErrors();
        $option = NominationSubclass::where('subclass', '407')->firstOrFail();
        $this->assertContains('407 - Training Visa', app(NominationCreation::class)->subclasses());
        $edit = route('configuration', ['tab' => 'nomination-subclasses', 'edit' => $option->id]);
        $this->get($edit)->assertOk()->assertSee('Edit Nomination Subclass');
        $this->patch(route('configuration.nomination-subclasses.update', $option), ['subclass' => '417', 'sbs_required' => false])->assertSessionHasNoErrors();
        $this->assertContains('417 - Working Holiday Visa', app(NominationCreation::class)->subclasses());
        $this->assertNotContains('407 - Training Visa', app(NominationCreation::class)->subclasses());
        $old = NominationSubclass::where('subclass', '186')->firstOrFail();
        $this->delete(route('configuration.nomination-subclasses.destroy', $old))->assertRedirect(route('configuration', ['tab' => 'nomination-subclasses']));
        $this->assertSame('186 - Employer Nomination Scheme Visa', $application->fresh()->nomination_pathway);
        $this->assertNotContains($application->nomination_pathway, app(NominationCreation::class)->subclasses());
        $this->assertContains($application->nomination_pathway, app(ApplicationSummary::class)->options($application, 'nomination_pathway'));
        $payload = ['category' => 'Nomination', 'external_nominee' => '1', 'nominee_name' => 'Nominee', 'visa_subclass' => $application->nomination_pathway];
        $this->postJson(route('organisations.applications.store', $organisation), $payload)->assertUnprocessable()->assertJsonValidationErrors('visa_subclass');
        NominationSubclass::query()->delete();
        $this->assertSame([], app(NominationCreation::class)->subclasses());
        $this->postJson(route('organisations.applications.store', $organisation), array_replace($payload, ['visa_subclass' => '500 - Student Visa']))
            ->assertUnprocessable()->assertJsonValidationErrors('visa_subclass');
    }

    public function test_catalogue_rejects_invalid_and_duplicate_choices_and_preserves_edit_input(): void
    {
        $this->actingAs(User::factory()->create());
        $option = NominationSubclass::where('subclass', '482')->firstOrFail();
        foreach (['482', '999', 'DAMA', '', ['491']] as $invalid) {
            $this->postJson(route('configuration.nomination-subclasses.store'), ['subclass' => $invalid, 'sbs_required' => false])
                ->assertUnprocessable()->assertJsonValidationErrors('subclass');
        }
        $this->patchJson(route('configuration.nomination-subclasses.update', $option), ['subclass' => '186', 'sbs_required' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('subclass');
        $url = route('configuration', ['tab' => 'nomination-subclasses', 'edit' => $option->id]);
        $this->from($url)->patch(route('configuration.nomination-subclasses.update', $option), ['subclass' => '407', 'sbs_required' => 'bad'])
            ->assertRedirect($url)->assertSessionHasErrors('sbs_required')->assertSessionHasInput('subclass', '407');
        $this->get($url)->assertOk()->assertSee('value="407" selected', false);
        $this->assertSame('482', $option->fresh()->subclass);
    }

    public function test_catalogue_mutations_require_configuration_access(): void
    {
        $option = NominationSubclass::firstOrFail();
        $this->postJson(route('configuration.nomination-subclasses.store'), [])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['account_type' => 'Counsellor']));
        $this->get(route('configuration', ['tab' => 'nomination-subclasses']))->assertForbidden();
        $this->postJson(route('configuration.nomination-subclasses.store'), [])->assertForbidden();
        $this->patchJson(route('configuration.nomination-subclasses.update', $option), [])->assertForbidden();
        $this->deleteJson(route('configuration.nomination-subclasses.destroy', $option))->assertForbidden();
    }
}
