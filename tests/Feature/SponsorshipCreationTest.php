<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorshipCreationTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(User $user): Organisation
    {
        return Organisation::create(['name' => 'Acme Legal Pty Ltd', 'trading_name' => 'Acme Trading', 'created_by_user_id' => $user->id]);
    }

    public function test_modal_has_only_the_two_required_sponsorship_choices_and_legacy_link_opens_it(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user)->get(route('organisations.show', $organisation))->assertOk()
            ->assertSee('aria-controls="sponsorship-create-dialog"', false)
            ->assertSee('Acme Legal Pty Ltd')->assertSee('Acme Trading')
            ->assertSee('type="radio" name="sponsor_type" value="Standard Business Sponsor" required', false)
            ->assertSee('type="radio" name="sponsor_type" value="Labour Agreement" required', false)
            ->assertDontSee('Accredited Business Sponsor');
        $this->get(route('organisations.applications.create', ['organisation' => $organisation, 'category' => 'SBS']))
            ->assertRedirect(route('organisations.show', ['organisation' => $organisation, 'add_sponsorship' => 1]));
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_each_choice_creates_an_assigned_record_and_opens_its_correct_reference_and_type(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $other = $this->organisation(User::factory()->create());
        $this->actingAs($user);
        foreach (['Standard Business Sponsor' => ['SBS', 'SBS Application', 'SBS Details'],
            'Labour Agreement' => ['LAB', 'Labour Agreement Application', 'Labour Agreement Details']] as $type => [$prefix, $label, $details]) {
            $response = $this->post(route('organisations.applications.store', $organisation), [
                '_add_sponsorship' => '1', 'category' => 'SBS', 'sponsor_type' => $type,
                'organisation_id' => $other->id, 'primary_counsellor_id' => $other->created_by_user_id,
                'client_id' => 999, 'status' => 'Active', 'priority' => 'H',
            ])->assertSessionHasNoErrors();
            $record = Application::latest('id')->firstOrFail();
            $response->assertRedirect(route('applications.show', $record));
            $this->assertSame($organisation->id, $record->organisation_id);
            $this->assertSame($user->id, $record->primary_counsellor_id);
            $this->assertSame($type, $record->sponsor_type);
            $this->assertSame('Not Started', $record->status);
            $this->assertSame('M', $record->priority);
            $this->assertNull($record->client_id);
            $this->assertNull($record->approval_date);
            $this->assertNull($record->expiry_date);
            $this->assertStringStartsWith($prefix.'-', $record->record_reference);
            $this->get(route('applications.show', $record))->assertOk()->assertSee($record->record_reference.' - '.$label)->assertSee($details);
            $this->get(route('organisations.show', $organisation))->assertOk()->assertSee($record->record_reference)->assertSee($type);
        }
        $this->assertDatabaseCount('applications', 2);
    }

    public function test_missing_or_unapproved_types_are_rejected_and_validation_reopens_the_modal(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user);
        foreach ([null, '', 'Accredited Business Sponsor', 'SBS', 'Custom sponsor', ['Labour Agreement']] as $type) {
            $this->postJson(route('organisations.applications.store', $organisation), ['category' => 'SBS', 'sponsor_type' => $type])
                ->assertUnprocessable()->assertJsonValidationErrors('sponsor_type');
        }
        $this->from(route('organisations.show', $organisation))->post(route('organisations.applications.store', $organisation), [
            'category' => 'SBS', 'sponsor_type' => 'Labour Agreement', '_add_sponsorship' => '1', 'approval_date' => 'invalid',
        ])->assertSessionHasErrors('approval_date');
        $this->get(route('organisations.show', $organisation))->assertOk()->assertSee('The sponsorship could not be created.')
            ->assertSee('value="Labour Agreement" required checked', false);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_creation_enforces_authentication_and_organisation_visibility(): void
    {
        $owner = User::factory()->create();
        $organisation = $this->organisation($owner);
        $payload = ['category' => 'SBS', 'sponsor_type' => 'Standard Business Sponsor'];
        $this->postJson(route('organisations.applications.store', $organisation), $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->get(route('organisations.applications.create', ['organisation' => $organisation, 'category' => 'SBS']))->assertNotFound();
        $this->postJson(route('organisations.applications.store', $organisation), $payload)->assertNotFound();
        $this->postJson(route('organisations.applications.store', 99999), $payload)->assertNotFound();
        $this->assertDatabaseCount('applications', 0);
    }
}
