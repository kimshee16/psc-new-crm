<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationTypeSetting;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use App\Support\PSC\ApplicationLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplicationLinksTest extends TestCase
{
    use RefreshDatabase;

    private function application(User $user, array $attributes = []): Application
    {
        return Application::create($attributes + [
            'organisation_id' => Organisation::create(['name' => 'Test organisation'])->id,
            'category' => 'Study', 'primary_counsellor_id' => $user->id,
        ]);
    }

    private function change(Application $source, Application $target, string $action = 'link')
    {
        return $this->post(route('applications.links.update', $source), [
            'linked_application_id' => $target->id, 'action' => $action,
        ]);
    }

    public function test_linked_items_remain_available_except_on_the_readonly_sbs_nomination_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (array_keys(ApplicationTypeSetting::types()) as $category) {
            ApplicationTypeSetting::create(['category' => $category, 'tabs' => []]);
            $application = $this->application($user, ['category' => $category]);
            if ($category === 'SBS') {
                $this->get(route('applications.show', $application))->assertOk()->assertDontSee('Link/Unlink Items');

                continue;
            }
            $this->get(route('applications.show', $application))->assertOk()
                ->assertSee('Linked Items')->assertSee('Application ID #')
                ->assertSee('Application description')->assertSee('Status')
                ->assertSee('Link/Unlink Items')->assertSee('No linked items available.')
                ->assertSee('application-links-dialog')->assertSee('Items linked to application')
                ->assertSee('Link another item')->assertSee('Cancel')->assertSee('Save');
        }
    }

    public function test_multiple_links_are_symmetric_idempotent_and_not_transitive(): void
    {
        $user = User::factory()->create();
        $a = $this->application($user);
        $b = $this->application($user, ['status' => 'Finalised', 'course' => 'Closed course']);
        $c = $this->application($user, ['status' => 'Withdrawn']);
        $this->actingAs($user);
        foreach ([[$a, $b], [$b, $a], [$a, $c], [$a, $b]] as [$source, $target]) {
            $this->change($source, $target)->assertRedirect(route('applications.links.edit', $source))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('application_links', 2);
        $this->get(route('applications.show', $a))->assertOk()
            ->assertViewHas('linkedItems', fn ($items) => $items->pluck('id')->all() === [$b->id, $c->id])
            ->assertSee('Closed course')->assertSee('Finalised')->assertSee('Withdrawn')
            ->assertSee('href="'.route('applications.show', $b).'"', false);
        $this->get(route('applications.show', $b))->assertOk()
            ->assertViewHas('linkedItems', fn ($items) => $items->pluck('id')->all() === [$a->id]);
        $this->change($b, $a, 'unlink')->assertSessionHasNoErrors();
        $this->change($b, $a, 'unlink')->assertSessionHasNoErrors();
        $this->get(route('applications.show', $a))->assertOk()
            ->assertViewHas('linkedItems', fn ($items) => $items->pluck('id')->all() === [$c->id]);
        $this->get(route('applications.show', $b))->assertOk()->assertViewHas('linkedItems', fn ($items) => $items->isEmpty());
        $this->assertDatabaseCount('applications', 3);
    }

    public function test_legacy_links_are_visible_in_both_directions_deduplicated_and_can_be_unlinked(): void
    {
        $user = User::factory()->create();
        $a = $this->application($user);
        $b = $this->application($user);
        $c = $this->application($user);
        $a->linked_application_id = $b->id;
        $a->save();
        $this->actingAs($user)->get(route('applications.show', $b))->assertOk()
            ->assertViewHas('linkedItems', fn ($items) => $items->pluck('id')->all() === [$a->id]);
        $b->linked_application_id = $a->id;
        $b->save();
        $c->linked_application_id = $a->id;
        $c->save();
        $this->change($a, $b)->assertSessionHasNoErrors();
        $this->get(route('applications.show', $a))->assertOk()
            ->assertViewHas('linkedItems', fn ($items) => $items->pluck('id')->all() === [$b->id, $c->id]);
        $this->change($b, $a, 'unlink')->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->linked_application_id);
        $this->assertNull($b->fresh()->linked_application_id);
        $this->assertSame($a->id, $c->fresh()->linked_application_id);
        $this->assertDatabaseCount('application_links', 0);
        $this->get(route('applications.show', $a))->assertOk()
            ->assertViewHas('linkedItems', fn ($items) => $items->pluck('id')->all() === [$c->id]);
    }

    public function test_linked_descriptions_follow_list_rules_and_are_escaped(): void
    {
        $user = User::factory()->create();
        $source = $this->application($user);
        $cases = [
            [['category' => 'Study', 'course' => '  '], 'Course TBD'],
            [['category' => 'Study', 'course' => '<script>unsafe()</script>'], '<script>unsafe()</script>'],
            [['category' => 'Visa', 'visa_subclass' => '500 - Student Visa'], '500 - Student Visa'],
            [['category' => 'Assessment', 'assessment_body' => 'ACS'], 'ACS'],
            [['category' => 'Nomination', 'nomination_pathway' => 'Employer Nomination Scheme Visa (186)'], 'Employer Nomination Scheme Visa (186)'],
            [['category' => 'Citizenship'], 'Australian Citizenship Application'],
            [['category' => 'ART Appeal'], 'ART Appeal'],
            [['category' => 'SBS'], 'Standard Business Sponsorship'],
        ];
        $this->actingAs($user);
        foreach ($cases as [$attributes, $description]) {
            $target = $this->application($user, $attributes);
            $this->change($source, $target)->assertSessionHasNoErrors();
            $this->get(route('applications.show', $source))->assertOk()->assertSee($description)
                ->assertDontSee('<script>unsafe()</script>', false);
        }
    }

    public function test_both_records_require_assignment_and_hidden_links_are_preserved(): void
    {
        $user = User::factory()->create(['account_type' => 'Admin']);
        $other = User::factory()->create();
        $source = $this->application($user);
        $hidden = $this->application($other, ['course' => 'Private course']);
        DB::table('application_links')->insert([
            'application_id' => $source->id, 'linked_application_id' => $hidden->id, 'created_at' => now(),
        ]);
        $this->get(route('applications.links.edit', $source))->assertRedirect(route('login'));
        $this->change($source, $hidden)->assertRedirect(route('login'));
        $this->actingAs($user);
        foreach (['applications.show', 'applications.links.edit'] as $route) {
            $this->get(route($route, $source))->assertOk()->assertDontSee('Private course')
                ->assertViewHas('linkedItems', fn ($items) => $items->isEmpty());
        }
        $this->get(route('applications.links.edit', $hidden))->assertNotFound();
        foreach (['link', 'unlink'] as $action) {
            $this->change($source, $hidden, $action)->assertNotFound();
            $this->change($hidden, $source, $action)->assertNotFound();
        }
        $this->assertDatabaseCount('application_links', 1);

        $secondary = $this->application($other, ['secondary_counsellor_id' => $user->id]);
        $agent = $this->application($other, ['migration_agent_id' => $user->id]);
        $this->change($source, $secondary)->assertSessionHasNoErrors();
        $this->change($source, $agent)->assertSessionHasNoErrors();
        $this->change($source, $secondary, 'unlink')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('application_links', 2);
    }

    public function test_search_is_paginated_literal_and_excludes_self_existing_links_and_inaccessible_records(): void
    {
        $user = User::factory()->create();
        $source = $this->application($user);
        $linked = $this->application($user, ['course' => 'Already linked']);
        $match = $this->application($user, ['course' => 'Literal %_ course', 'status' => 'Finalised']);
        $this->application(User::factory()->create(), ['course' => 'Private %_ course']);
        for ($i = 0; $i < 16; $i++) {
            $this->application($user, ['course' => 'Course '.$i]);
        }
        $this->actingAs($user);
        $this->change($source, $linked)->assertSessionHasNoErrors();
        $this->get(route('applications.links.edit', $source))->assertOk()
            ->assertViewHas('candidates', fn ($items) => $items->total() === 17 && $items->count() === 15
                && ! $items->pluck('id')->contains($source->id) && ! $items->pluck('id')->contains($linked->id));
        foreach (['%_', 'finalised'] as $search) {
            $this->get(route('applications.links.edit', ['application' => $source, 'search' => $search]))->assertOk()
                ->assertViewHas('candidates', fn ($items) => $items->pluck('id')->all() === [$match->id]);
        }
        $this->get(route('applications.links.edit', ['application' => $source, 'search' => (string) $match->id]))
            ->assertOk()->assertViewHas('candidates', fn ($items) => $items->pluck('id')->contains($match->id));
        $this->get(route('applications.links.edit', ['application' => $source, 'search' => 'Course', 'page' => 2]))
            ->assertOk()->assertSee('search=Course', false)->assertViewHas('candidates', fn ($items) => $items->count() === 2);
        $this->getJson(route('applications.links.edit', ['application' => $source, 'search' => ['invalid']]))
            ->assertUnprocessable();
    }

    public function test_invalid_actions_self_links_and_missing_targets_do_not_mutate_links(): void
    {
        $user = User::factory()->create();
        $source = $this->application($user);
        $this->actingAs($user);
        foreach ([
            ['action' => 'link', 'linked_application_id' => $source->id],
            ['action' => 'delete', 'linked_application_id' => 2],
            ['action' => 'link', 'linked_application_id' => ['invalid']],
            ['action' => 'link'],
        ] as $payload) {
            $this->postJson(route('applications.links.update', $source), $payload)->assertUnprocessable();
        }
        $this->postJson(route('applications.links.update', $source), ['action' => 'link', 'linked_application_id' => 999999])
            ->assertNotFound();
        $this->assertDatabaseCount('application_links', 0);
    }

    public function test_deleting_an_application_cleans_up_links_without_deleting_other_applications(): void
    {
        $user = User::factory()->create();
        $source = $this->application($user);
        $target = $this->application($user);
        app(ApplicationLinks::class)->change($user, $source->id, $target->id, true);
        $target->delete();
        $this->assertDatabaseCount('application_links', 0);
        $this->assertDatabaseHas('applications', ['id' => $source->id]);
    }

    public function test_popup_suggests_only_same_client_and_looks_up_other_clients_by_exact_id(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'client_id' => 7500, 'first_name' => 'PSC75', 'surname' => 'Client',
            'mobile' => '', 'email' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => $user->name,
        ]);
        $source = $this->application($user, ['client_id' => $client->id, 'organisation_id' => null]);
        $same = $this->application($user, ['client_id' => $client->id, 'organisation_id' => null]);
        $other = $this->application($user, ['category' => 'Nomination']);
        $hidden = $this->application(User::factory()->create(), ['client_id' => $client->id]);
        $existing = $this->application($user, ['client_id' => $client->id]);
        app(ApplicationLinks::class)->change($user, $source->id, $existing->id, true);
        $this->actingAs($user)->getJson(route('applications.links.edit', $source))->assertOk()
            ->assertJsonCount(1, 'candidates.data')->assertJsonPath('candidates.data.0.id', $same->id)
            ->assertJsonPath('linked.0.id', $existing->id);
        $this->getJson(route('applications.links.edit', $other))->assertOk()->assertJsonCount(0, 'candidates.data');
        $this->getJson(route('applications.links.edit', ['application' => $source, 'application_id' => $other->id]))
            ->assertOk()->assertJsonCount(1, 'candidates.data')->assertJsonPath('candidates.data.0.id', $other->id);
        foreach ([$source->id, $hidden->id, $existing->id, 999999] as $id) {
            $this->getJson(route('applications.links.edit', ['application' => $source, 'application_id' => $id]))
                ->assertOk()->assertJsonCount(0, 'candidates.data');
        }
        $this->getJson(route('applications.links.edit', ['application' => $source, 'application_id' => 'bad']))->assertUnprocessable();
        for ($i = 0; $i < 16; $i++) {
            $this->application($user, ['client_id' => $client->id]);
        }
        $this->getJson(route('applications.links.edit', $source))->assertOk()
            ->assertJsonCount(15, 'candidates.data')->assertJsonPath('candidates.total', 17);
        $this->getJson(route('applications.links.edit', ['application' => $source, 'page' => 2]))->assertOk()
            ->assertJsonCount(2, 'candidates.data')->assertJsonPath('candidates.current_page', 2);
        $this->assertDatabaseCount('application_links', 1);
    }

    public function test_popup_saves_multiple_links_and_removals_reciprocally_preserving_other_pairs(): void
    {
        $user = User::factory()->create();
        $source = $this->application($user, ['category' => 'Nomination']);
        $old = $this->application($user);
        $a = $this->application($user);
        $b = $this->application($user);
        $unrelated = $this->application($user);
        $hidden = $this->application(User::factory()->create());
        $old->linked_application_id = $source->id;
        $old->save();
        app(ApplicationLinks::class)->change($user, $source->id, $unrelated->id, true);
        DB::table('application_links')->insert(['application_id' => $source->id, 'linked_application_id' => $hidden->id, 'created_at' => now()]);
        $this->actingAs($user);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('applications.links.update', $source), [
                'action' => 'save', 'add' => [$a->id, $b->id], 'remove' => [$old->id],
            ])->assertOk();
        }
        $this->assertNull($old->fresh()->linked_application_id);
        $this->assertDatabaseCount('application_links', 4);
        foreach ([$a, $b, $unrelated] as $target) {
            $this->getJson(route('applications.links.edit', $target))->assertOk()->assertJsonPath('linked.0.id', $source->id);
        }
        $this->getJson(route('applications.links.edit', $old))->assertOk()->assertJsonCount(0, 'linked');
        $this->postJson(route('applications.links.update', $source), ['action' => 'save', 'add' => [], 'remove' => []])->assertOk();
        $this->assertDatabaseCount('application_links', 4);
    }

    public function test_batch_rejects_invalid_or_inaccessible_targets_without_partial_updates(): void
    {
        $user = User::factory()->create();
        $source = $this->application($user);
        $target = $this->application($user);
        $hidden = $this->application(User::factory()->create());
        $this->actingAs($user);
        foreach ([
            ['add' => [$source->id], 'remove' => []],
            ['add' => [$target->id, $target->id], 'remove' => []],
            ['add' => [$target->id], 'remove' => [$target->id]],
            ['add' => ['bad'], 'remove' => []],
            ['add' => [], 'remove' => [$source->id]],
            ['add' => []],
        ] as $payload) {
            $this->postJson(route('applications.links.update', $source), ['action' => 'save'] + $payload)->assertUnprocessable();
        }
        foreach ([$hidden->id, 999999] as $id) {
            $this->postJson(route('applications.links.update', $source), [
                'action' => 'save', 'add' => [$target->id, $id], 'remove' => [],
            ])->assertNotFound();
            $this->postJson(route('applications.links.update', $source), [
                'action' => 'save', 'add' => [$target->id], 'remove' => [$id],
            ])->assertNotFound();
        }
        $this->assertDatabaseCount('application_links', 0);
    }
}
