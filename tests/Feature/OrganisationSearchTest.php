<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_discoverable_requires_login_and_waits_for_criteria(): void
    {
        $this->get(route('organisations.search'))->assertRedirect(route('login'));
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $this->actingAs($user)->get(route('organisations'))->assertOk()
            ->assertSee(route('organisations.search'), false)->assertSee('Organisation search');
        foreach ([[], ['contact_name' => '   '], ['page' => 2]] as $filters) {
            $this->get(route('organisations.search', $filters))->assertOk()
                ->assertSee('Organisation Search')->assertSee('View My Organisations')->assertSee('Create Organisation')
                ->assertViewHas('hasSearch', false)->assertViewHas('organisations', null)
                ->assertDontSee($organisation->name);
        }
    }

    public function test_global_search_finds_unassigned_organisations_by_name_and_contact_details_without_duplicates(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $match = $this->organisation($other);
        $match->contacts()->create(['name' => 'Michelle Jane Martano', 'email' => 'michelle@example.test', 'phone' => '+61 (450) 273-883']);
        $match->contacts()->create(['name' => 'Michelle Other', 'email' => 'other@example.test']);
        $this->organisation($other, ['name' => 'Different employer', 'trading_name' => null]);
        $this->actingAs($viewer);

        foreach ([
            ['id' => $match->reference], ['id' => (string) $match->id],
            ['organisation_name' => 'example legal'], ['organisation_name' => 'TRADING'],
            ['contact_name' => 'michelle'], ['contact_name' => 'MARTANO'],
            ['contact_mobile' => '450273883'], ['contact_mobile' => '+61 450 273 883'],
            ['contact_email' => 'MICHELLE@EXAMPLE'],
        ] as $filters) {
            $this->get(route('organisations.search', $filters))->assertOk()
                ->assertSee('1 organisation found')->assertSee('Michelle Jane Martano')
                ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$match->id]);
        }
    }

    public function test_combined_criteria_match_the_same_contact_and_treat_wildcards_literally(): void
    {
        $user = User::factory()->create();
        $match = $this->organisation($user);
        $match->contacts()->create(['name' => 'Alice Example', 'email' => 'alice@example.test', 'phone' => '0412 555 001']);
        $match->contacts()->create(['name' => 'Bob Example', 'email' => 'bob@example.test', 'phone' => '0412 555 002']);
        $this->actingAs($user);
        $this->get(route('organisations.search', ['organisation_name' => 'Example', 'contact_name' => 'Alice', 'contact_email' => 'alice@', 'contact_mobile' => '0412555001']))
            ->assertOk()->assertViewHas('organisations', fn ($rows) => $rows->total() === 1);
        foreach ([
            ['contact_name' => 'Alice', 'contact_email' => 'bob@'],
            ['contact_name' => 'Alice', 'contact_mobile' => '0412555002'],
            ['organisation_name' => 'Missing', 'contact_email' => 'alice@'],
            ['contact_name' => '%_!'], ['contact_email' => '%_!'], ['contact_mobile' => '%_!'],
        ] as $filters) {
            $this->get(route('organisations.search', $filters))->assertOk()
                ->assertSee('No organisations match the current search.')
                ->assertViewHas('organisations', fn ($rows) => $rows->total() === 0);
        }
        $match->contacts()->create(['name' => 'Literal %_! Contact']);
        $this->get(route('organisations.search', ['contact_name' => '%_!']))->assertOk()
            ->assertViewHas('organisations', fn ($rows) => $rows->total() === 1);
    }

    public function test_global_results_open_readonly_details_without_granting_edit_or_application_access(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $organisation = $this->organisation($owner);
        $contact = $organisation->contacts()->create(['name' => 'Michelle Martano', 'phone' => '0412 555 883']);
        $this->get(route('organisations.search.show', $organisation))->assertRedirect(route('login'));
        $this->actingAs($viewer)->get(route('organisations.search.show', $organisation))->assertOk()
            ->assertSee($organisation->reference)->assertSee($contact->name)->assertSee($contact->phone)
            ->assertDontSee('Edit details')->assertDontSee('Open full record')->assertDontSee('Add sponsorship');
        $this->get(route('organisations.show', $organisation))->assertNotFound();
        $this->get(route('organisations.edit', $organisation))->assertNotFound();
        $this->patch(route('organisations.update', $organisation), ['legal_name' => 'Changed'])->assertNotFound();
        $this->post(route('organisations.contacts.store', $organisation), ['name' => 'Changed'])->assertNotFound();
        $this->actingAs($owner)->get(route('organisations.search.show', $organisation))->assertOk()->assertSee('Open full record');
    }

    public function test_existing_list_and_csv_can_search_contacts_while_retaining_their_assignment_scope(): void
    {
        $viewer = User::factory()->create();
        $visible = $this->organisation($viewer);
        $hidden = $this->organisation(User::factory()->create(), ['name' => 'Unassigned employer']);
        foreach ([$visible, $hidden] as $organisation) {
            $organisation->contacts()->create(['name' => 'Michelle Martano', 'email' => 'michelle@example.test', 'phone' => '+61 (450) 273-883']);
        }
        foreach (['Michelle', 'Martano', 'michelle@example.test', '450273883'] as $search) {
            $this->actingAs($viewer)->get(route('organisations', compact('search')))->assertOk()
                ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$visible->id]);
            $csv = $this->get(route('organisations.export', compact('search')))->assertOk()->streamedContent();
            $this->assertStringContainsString($visible->reference, $csv);
            $this->assertStringNotContainsString($hidden->reference, $csv);
        }
    }

    public function test_search_pagination_preserves_criteria_and_rejects_malformed_input(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 21; $i++) {
            $this->organisation($user);
        }
        $this->actingAs($user)->get(route('organisations.search', ['organisation_name' => 'Example', 'page' => 2]))
            ->assertOk()->assertViewHas('organisations', fn ($rows) => $rows->total() === 21 && $rows->count() === 1
                && str_contains($rows->previousPageUrl(), 'organisation_name=Example'));
        foreach (['id', 'organisation_name', 'contact_name', 'contact_mobile', 'contact_email'] as $field) {
            foreach ([['invalid'], str_repeat('x', 256)] as $value) {
                $this->getJson(route('organisations.search', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->getJson(route('organisations.search', ['page' => 0]))->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    private function organisation(User $owner, array $attributes = []): Organisation
    {
        return Organisation::create($attributes + ['name' => 'Example Legal Pty Ltd', 'trading_name' => 'Example Trading', 'created_by_user_id' => $owner->id]);
    }
}
