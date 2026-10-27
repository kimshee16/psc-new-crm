<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Client;
use App\Models\User;
use App\Support\PSC\CriticalDateFormatting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationsListTest extends TestCase
{
    use RefreshDatabase;

    private function application(User $user, array $attributes = [], array $clientAttributes = []): Application
    {
        $client = Client::create(array_merge([
            'client_id' => 1000 + Client::count(), 'first_name' => 'Ana', 'middle_name' => 'Maria',
            'surname' => 'Santos', 'mobile' => '', 'email' => '', 'nationality' => '',
            'current_location' => '', 'admin_office' => 'Melbourne', 'primary_counsellor' => $user->name,
        ], $clientAttributes));

        return Application::create(array_merge([
            'client_id' => $client->id, 'category' => 'Study', 'priority' => 'M',
            'status' => 'In Progress', 'primary_counsellor_id' => $user->id,
        ], $attributes));
    }

    public function test_list_and_export_require_authentication(): void
    {
        foreach (['/applications', '/applications/export', '/applications/1'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_only_active_applications_assigned_in_any_role_are_visible_even_for_admins(): void
    {
        $user = User::factory()->create(['account_type' => 'Admin']);
        $other = User::factory()->create(['name' => $user->name]);
        $primary = $this->application($user);
        $secondary = $this->application($other, ['secondary_counsellor_id' => $user->id]);
        $agent = $this->application($other, ['migration_agent_id' => $user->id]);
        $multiple = $this->application($user, ['secondary_counsellor_id' => $user->id, 'migration_agent_id' => $user->id]);
        $this->application($other, [], ['first_name' => 'Unassigned']);
        $this->application($user, ['status' => 'Finalised']);
        $this->application($user, ['status' => 'Withdrawn']);
        $this->application($user, ['status' => ' finalised ']);

        $this->actingAs($user)->get('/applications')->assertOk()
            ->assertViewHas('applications', fn ($rows) => $rows->pluck('id')->all() === [$multiple->id, $agent->id, $secondary->id, $primary->id])
            ->assertDontSee('Unassigned')->assertSee('target="_blank"', false)
            ->assertSee('Ana Maria Santos');
        $csv = $this->get('/applications/export')->assertOk()->streamedContent();
        $this->assertCount(5, $this->csvRows($csv));
        $this->assertStringNotContainsString('Unassigned', $csv);
    }

    public function test_application_descriptions_follow_category_rules_and_are_searchable(): void
    {
        $user = User::factory()->create();
        $cases = [
            [['category' => 'Study'], 'Course TBD'],
            [['category' => 'Study', 'course' => 'Diploma of Commercial cookery'], 'Diploma of Commercial cookery'],
            [['category' => 'Visa', 'visa_subclass' => '500 - Student Visa'], '500 - Student Visa'],
            [['category' => 'Citizenship'], 'Australian Citizenship Application'],
            [['category' => 'SBS'], 'Standard Business Sponsorship'],
            [['category' => 'Nomination', 'nomination_pathway' => 'Employer Nomination Scheme Visa (186)'], 'Employer Nomination Scheme Visa (186)'],
            [['category' => 'Assessment', 'assessment_body' => 'Vetassess'], 'Vetassess'],
        ];
        foreach ($cases as [$attributes, $description]) {
            $application = $this->application($user, $attributes);
            $this->actingAs($user)->get('/applications?'.http_build_query(['description' => $description]))
                ->assertOk()->assertViewHas('applications', fn ($rows) => $rows->pluck('id')->all() === [$application->id]);
        }
    }

    public function test_all_text_filters_and_date_range_are_combined_in_list_and_csv(): void
    {
        $user = User::factory()->create(['name' => 'Jessica Morgan']);
        $secondary = User::factory()->create(['name' => 'Priya Raman']);
        $agent = User::factory()->create(['name' => 'Nina Patel']);
        $match = $this->application($user, [
            'category' => 'Visa', 'visa_subclass' => '500 - Student Visa', 'priority' => 'H',
            'critical_date' => '2026-10-22', 'secondary_counsellor_id' => $secondary->id, 'migration_agent_id' => $agent->id,
        ]);
        $this->application($user, ['critical_date' => null]);
        $filters = [
            'id' => $match->id, 'client_name' => 'ana maria', 'category' => 'visa', 'description' => 'Student',
            'status' => 'progress', 'primary_counsellor' => 'jessica', 'secondary_counsellor' => 'priya',
            'migration_agent' => 'nina', 'priority' => ['H', 'M'], 'date_from' => '2026-10-22', 'date_to' => '2026-10-22',
        ];
        $this->actingAs($user)->get('/applications?'.http_build_query($filters))->assertOk()
            ->assertViewHas('applications', fn ($rows) => $rows->pluck('id')->all() === [$match->id]);
        $rows = $this->csvRows($this->get('/applications/export?'.http_build_query($filters))->assertOk()->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertSame([(string) $match->id, 'Ana Maria Santos', 'Visa', '500 - Student Visa', '2026-10-22', 'H', 'In Progress', 'Jessica Morgan', 'Priya Raman', 'Nina Patel'], $rows[1]);
    }

    public function test_each_text_filter_actually_excludes_nonmatching_rows_and_treats_wildcards_literally(): void
    {
        $user = User::factory()->create();
        $this->application($user);
        foreach (['id', 'client_name', 'category', 'description', 'status', 'primary_counsellor', 'secondary_counsellor', 'migration_agent'] as $column) {
            $this->actingAs($user)->get('/applications?'.http_build_query([$column => '%_']))->assertOk()
                ->assertViewHas('applications', fn ($rows) => $rows->total() === 0);
        }
    }

    public function test_numeric_priority_and_date_sorts_are_semantic_and_null_dates_are_last(): void
    {
        $user = User::factory()->create();
        $low = $this->application($user, ['id' => 2, 'priority' => 'L', 'critical_date' => null]);
        $high = $this->application($user, ['id' => 10, 'priority' => 'H', 'critical_date' => '2026-10-22']);
        $medium = $this->application($user, ['priority' => 'M', 'critical_date' => '2026-10-23']);
        foreach ([
            ['id', 'asc', [$low->id, $high->id, $medium->id]],
            ['id', 'desc', [$medium->id, $high->id, $low->id]],
            ['priority', 'desc', [$high->id, $medium->id, $low->id]],
            ['priority', 'asc', [$low->id, $medium->id, $high->id]],
            ['critical_date', 'asc', [$high->id, $medium->id, $low->id]],
            ['critical_date', 'desc', [$medium->id, $high->id, $low->id]],
        ] as [$sort, $direction, $ids]) {
            $this->actingAs($user)->get('/applications?'.http_build_query(compact('sort', 'direction')))->assertOk()
                ->assertViewHas('applications', fn ($rows) => $rows->pluck('id')->all() === $ids);
        }
    }

    public function test_text_sorting_is_case_insensitive_and_has_stable_id_tiebreaks(): void
    {
        $user = User::factory()->create();
        $first = $this->application($user, ['course' => 'alpha'], ['first_name' => 'alice']);
        $second = $this->application($user, ['course' => 'Bravo'], ['first_name' => 'Bob']);
        foreach (['client_name', 'description'] as $sort) {
            foreach (['asc' => [$first->id, $second->id], 'desc' => [$second->id, $first->id]] as $direction => $ids) {
                $this->actingAs($user)->get('/applications?'.http_build_query(compact('sort', 'direction')))->assertOk()
                    ->assertViewHas('applications', fn ($rows) => $rows->pluck('id')->all() === $ids);
            }
        }
    }

    public function test_pagination_supports_all_sizes_and_export_includes_all_filtered_pages(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 23; $i++) {
            $this->application($user);
        }
        foreach ([10, 20, 50] as $size) {
            $this->actingAs($user)->get('/applications?per_page='.$size)->assertOk()
                ->assertViewHas('applications', fn ($rows) => $rows->count() === min(23, $size) && $rows->total() === 23);
        }
        $this->get('/applications?per_page=10&page=3&category=Study')->assertOk()
            ->assertViewHas('applications', fn ($rows) => $rows->count() === 3 && str_contains($rows->previousPageUrl(), 'category=Study'));
        $rows = $this->csvRows($this->get('/applications/export?per_page=10&page=3&category=Study')->streamedContent());
        $this->assertCount(24, $rows);
    }

    public function test_record_action_is_assignment_scoped_and_supports_closed_records(): void
    {
        $user = User::factory()->create();
        $own = $this->application($user, ['status' => 'Finalised']);
        $other = $this->application(User::factory()->create());
        $this->actingAs($user)->get('/applications/'.$own->id)->assertOk()->assertSee('Course TBD');
        $this->get('/applications/'.$other->id)->assertNotFound();
        $this->get('/applications/999999')->assertNotFound();
    }

    public function test_invalid_query_parameters_are_rejected_on_list_and_export(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['sort' => 'invalid'], ['direction' => 'invalid'], ['per_page' => 500], ['page' => -1], ['priority' => ['Z']], ['priority' => 'H'], ['client_name' => ['bad']], ['date_from' => 'not-a-date'], ['date_from' => '2026-10-23', 'date_to' => '2026-10-22']] as $filters) {
            foreach (['/applications', '/applications/export'] as $url) {
                $this->getJson($url.'?'.http_build_query($filters))->assertUnprocessable();
            }
        }
    }

    public function test_csv_escapes_formulas_and_preserves_commas_quotes_and_newlines(): void
    {
        $user = User::factory()->create();
        $this->application($user, ['course' => "=HYPERLINK(\"bad\")\nsecond line"], ['first_name' => 'Ana, Maria']);
        $rows = $this->csvRows($this->actingAs($user)->get('/applications/export')->streamedContent());
        $this->assertSame('Ana, Maria Maria Santos', $rows[1][1]);
        $this->assertSame("'=HYPERLINK(\"bad\")\nsecond line", $rows[1][3]);
    }

    public function test_date_formatting_handles_lapsed_missing_and_configured_category_boundaries(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->startOfDay());
        $formatter = app(CriticalDateFormatting::class);
        $this->assertSame('red', $formatter->forDate('2026-10-21', 'Study')['tone']);
        $this->assertSame('none', $formatter->forDate(null, 'Study')['tone']);
        $this->assertSame('none', $formatter->forDate('2026-10-23', 'Study')['tone']);
        // These are test thresholds, not an assertion of PSC-4's unconfirmed rules.
        config(['applications.critical_date_thresholds.Study' => ['red' => 7, 'amber' => 14]]);
        $this->assertSame('red', $formatter->forDate('2026-10-29', 'Study')['tone']);
        $this->assertSame('amber', $formatter->forDate('2026-10-30', 'Study')['tone']);
        $this->assertSame('amber', $formatter->forDate('2026-11-05', 'Study')['tone']);
        $this->assertSame('none', $formatter->forDate('2026-11-06', 'Study')['tone']);
        $this->assertSame('none', $formatter->forDate('2026-10-29', 'Visa')['tone']);
        $user = User::factory()->create();
        $this->application($user, ['critical_date' => '2026-10-21']);
        $this->actingAs($user)->get('/applications')->assertSee('data-tone="red"', false)->assertSee('Lapsed');
    }

    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, ltrim($csv, "\xEF\xBB\xBF"));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
