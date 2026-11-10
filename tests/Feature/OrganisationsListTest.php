<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationsListTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(User $user, array $attributes = []): Organisation
    {
        return Organisation::create($attributes + [
            'name' => 'Example Legal Pty Ltd', 'trading_name' => 'Example Trading', 'created_by_user_id' => $user->id,
        ]);
    }

    private function sbs(Organisation $organisation, User $user, array $attributes = []): Application
    {
        return $organisation->applications()->create($attributes + [
            'category' => 'SBS', 'status' => 'Active', 'sponsor_type' => 'Standard',
            'expiry_date' => '2027-03-04', 'primary_counsellor_id' => $user->id,
        ]);
    }

    public function test_list_and_export_require_authentication_and_keep_existing_organisation_access_rules(): void
    {
        foreach (['organisations', 'organisations.export'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->organisation($user);
        $assigned = $this->organisation($other, ['name' => 'Assigned Organisation']);
        $this->sbs($assigned, $other, ['secondary_counsellor_id' => $user->id]);
        $hidden = $this->organisation($other, ['name' => 'Hidden Organisation']);
        $this->actingAs($user)->get(route('organisations'))->assertOk()
            ->assertSee('Organisations')->assertSee('SBS held')->assertSee('Renew')->assertSee('Action')
            ->assertSee('Download CSV')->assertSee('href="'.route('organisations').'"', false)
            ->assertSee('aria-label="Open organisation '.$own->reference.'"', false)
            ->assertSee('href="'.route('organisations.show', $assigned).'"', false)
            ->assertViewHas('organisations', fn ($rows) => $rows->total() === 2)
            ->assertDontSee($hidden->name);
        $csv = $this->get(route('organisations.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString($assigned->reference, $csv);
        $this->assertStringNotContainsString($hidden->name, $csv);
        $this->get(route('organisations.show', $hidden))->assertNotFound();
    }

    public function test_active_sbs_and_renewal_flags_use_type_specific_inclusive_calendar_month_windows(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->startOfDay());
        $user = User::factory()->create();
        $cases = [
            ['Standard', '2027-03-04', 'Active', 'SBS', 1, 1],
            ['Standard Business Sponsor', '2027-03-05', 'Active', 'SBS', 1, 0],
            ['Accredited', '2027-01-04', 'Active', 'SBS', 1, 1],
            ['Accredited Business Sponsor', '2027-01-05', 'Active', 'SBS', 1, 0],
            [' accredited sponsor ', '2026-11-04', ' active ', 'SBS', 0, 0],
            ['Standard', '2026-11-03', 'Active', 'SBS', 0, 0],
            ['Standard', null, 'Active', 'SBS', 1, 0],
            ['Unknown', '2026-12-01', 'Active', 'SBS', 1, 0],
            ['Standard', '2026-12-01', 'In Progress', 'SBS', 0, 0],
            ['Standard', '2026-12-01', 'Not Started', 'SBS', 0, 0],
            ['Standard', '2026-12-01', 'Withdrawn', 'SBS', 0, 0],
            ['Standard', '2026-12-01', 'Finalised', 'SBS', 0, 0],
            ['Standard', '2026-12-01', 'Active', 'Nomination', 0, 0],
        ];
        $expected = [];
        foreach ($cases as [$type, $expiry, $status, $category, $held, $renew]) {
            $organisation = $this->organisation($user);
            $this->sbs($organisation, $user, ['sponsor_type' => $type, 'expiry_date' => $expiry, 'status' => $status, 'category' => $category]);
            $expected[$organisation->id] = [$held, $renew];
        }
        $empty = $this->organisation($user);
        $expected[$empty->id] = [0, 0];
        // Multiple SBS records must neither duplicate an organisation nor hide a qualifying SBS.
        $multiple = $this->organisation($user);
        $this->sbs($multiple, $user, ['expiry_date' => '2026-12-01']);
        $this->sbs($multiple, $user, ['expiry_date' => '2028-12-01']);
        $this->sbs($multiple, $user, ['status' => 'Withdrawn']);
        $expected[$multiple->id] = [1, 1];
        $rows = $this->actingAs($user)->get(route('organisations', ['per_page' => 50]))->assertOk()
            ->viewData('organisations');
        $this->assertCount(count($expected), $rows);
        foreach ($rows as $row) {
            $this->assertSame($expected[$row->id], [(int) $row->sbs_held, (int) $row->renew], $row->reference);
        }
    }

    public function test_month_end_windows_do_not_overflow_into_the_following_month(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->startOfDay());
        $user = User::factory()->create();
        $boundary = $this->organisation($user);
        $this->sbs($boundary, $user, ['expiry_date' => '2027-02-28']);
        $outside = $this->organisation($user);
        $this->sbs($outside, $user, ['expiry_date' => '2027-03-01']);
        $this->actingAs($user)->get(route('organisations', ['renew' => 'flag']))->assertOk()
            ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$boundary->id]);
    }

    public function test_each_column_filter_and_combined_filters_match_the_csv(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->startOfDay());
        $user = User::factory()->create();
        $match = $this->organisation($user, ['name' => 'Acme Legal Pty Ltd', 'trading_name' => 'Acme Trading']);
        $this->sbs($match, $user);
        $noSbs = $this->organisation($user, ['name' => 'Other Legal', 'trading_name' => 'Other Trading']);
        foreach ([
            ['id' => $match->reference], ['id' => (string) $match->id], ['trading_name' => 'acme'],
            ['legal_name' => 'LEGAL PTY'], ['sbs_held' => 'Y'], ['renew' => 'flag'],
            ['id' => $match->reference, 'trading_name' => 'acme', 'legal_name' => 'legal', 'sbs_held' => 'Y', 'renew' => 'flag'],
        ] as $filters) {
            $this->actingAs($user)->get(route('organisations', $filters))->assertOk()
                ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$match->id]);
            $rows = $this->csvRows($this->get(route('organisations.export', $filters))->assertOk()->streamedContent());
            $this->assertSame([
                ['Organisation ID', 'Trading name', 'Legal name', 'SBS held', 'Renew'],
                [$match->reference, 'Acme Trading', 'Acme Legal Pty Ltd', 'Y', 'Flag'],
            ], $rows);
        }
        foreach ([['sbs_held' => 'N'], ['renew' => 'no_flag']] as $filters) {
            $this->get(route('organisations', $filters))->assertOk()
                ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$noSbs->id]);
        }
        foreach (['id', 'trading_name', 'legal_name', 'search'] as $filter) {
            $this->get(route('organisations', [$filter => '%_!']))->assertOk()->assertSee('No organisations found.')
                ->assertViewHas('organisations', fn ($rows) => $rows->total() === 0);
        }
        $literal = $this->organisation($user, ['trading_name' => '100%_! Trading']);
        $this->get(route('organisations', ['trading_name' => '%_!']))->assertOk()
            ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$literal->id]);
    }

    public function test_sorting_defaults_to_trading_name_and_supports_every_column_in_both_directions(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->startOfDay());
        $user = User::factory()->create();
        $first = $this->organisation($user, ['trading_name' => 'zebra', 'name' => 'Alpha Legal']);
        $second = $this->organisation($user, ['trading_name' => 'Bravo', 'name' => 'zulu Legal']);
        $this->sbs($second, $user);
        $this->actingAs($user)->get(route('organisations'))->assertOk()
            ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$second->id, $first->id]);
        foreach (['id', 'trading_name', 'legal_name', 'sbs_held', 'renew'] as $sort) {
            $asc = $sort === 'trading_name' ? [$second->id, $first->id] : [$first->id, $second->id];
            foreach (['asc' => $asc, 'desc' => array_reverse($asc)] as $direction => $ids) {
                $filters = compact('sort', 'direction');
                $this->get(route('organisations', $filters))->assertOk()
                    ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === $ids);
                $csv = $this->csvRows($this->get(route('organisations.export', $filters))->assertOk()->streamedContent());
                $this->assertSame(array_map(fn ($id) => 'ORG-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT), $ids), array_column(array_slice($csv, 1), 0));
            }
        }
        $same = $this->organisation($user, ['trading_name' => 'BRAVO']);
        $missing = $this->organisation($user, ['trading_name' => null]);
        $this->get(route('organisations'))->assertOk()
            ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$second->id, $same->id, $first->id, $missing->id]);
    }

    public function test_pagination_preserves_filters_and_csv_exports_every_matching_page(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 23; $i++) {
            $this->organisation($user);
        }
        $this->organisation($user, ['trading_name' => 'Excluded']);
        foreach ([10, 20, 50] as $size) {
            $this->actingAs($user)->get(route('organisations', ['per_page' => $size, 'trading_name' => 'Example']))->assertOk()
                ->assertViewHas('organisations', fn ($rows) => $rows->count() === min(23, $size) && $rows->total() === 23);
        }
        $filters = ['per_page' => 10, 'page' => 3, 'trading_name' => 'Example', 'sort' => 'id', 'direction' => 'desc'];
        $this->get(route('organisations', $filters))->assertOk()
            ->assertViewHas('organisations', fn ($rows) => $rows->count() === 3
                && str_contains($rows->previousPageUrl(), 'trading_name=Example')
                && str_contains($rows->previousPageUrl(), 'direction=desc'));
        $rows = $this->csvRows($this->get(route('organisations.export', $filters))->assertOk()->streamedContent());
        $this->assertCount(24, $rows);
        $this->assertSame('ORG-000023', $rows[1][0]);
        $this->assertSame('ORG-000001', $rows[23][0]);
    }

    public function test_csv_preserves_special_characters_and_neutralises_spreadsheet_formulas(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user, ['trading_name' => "=HYPERLINK(\"bad\")\nnext line", 'name' => 'Café, "Legal"']);
        $rows = $this->csvRows($this->actingAs($user)->get(route('organisations.export'))->assertOk()->streamedContent());
        $this->assertSame([$organisation->reference, "'=HYPERLINK(\"bad\")\nnext line", 'Café, "Legal"', 'N', 'No flag'], $rows[1]);
    }

    public function test_invalid_filters_sort_and_pagination_are_rejected_on_both_endpoints(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['id' => ['bad']], ['trading_name' => ['bad']], ['legal_name' => str_repeat('x', 256)],
            ['sort' => 'unknown'], ['direction' => 'unknown'], ['per_page' => 100], ['page' => 0],
            ['sbs_held' => 'unknown'], ['renew' => 'unknown'], ['renew' => ['flag']]] as $filters) {
            foreach (['organisations', 'organisations.export'] as $route) {
                $this->getJson(route($route, $filters))->assertUnprocessable();
            }
        }
    }

    public function test_sbs_can_be_marked_active_using_the_existing_summary_form(): void
    {
        $user = User::factory()->create();
        $organisation = $this->organisation($user);
        $sbs = $this->sbs($organisation, $user, ['status' => 'Not Started']);
        $this->actingAs($user)->get(route('applications.show', $sbs))->assertOk()->assertSee('value="Active"', false);
        $this->patch(route('applications.update', $sbs), [
            'status' => 'Active', 'priority' => 'M', 'primary_counsellor_id' => $user->id,
            'migration_agent_id' => $user->id, 'trn' => 'ABC1234567890',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Active', $sbs->fresh()->status);
        $this->get(route('organisations', ['sbs_held' => 'Y']))->assertOk()
            ->assertViewHas('organisations', fn ($rows) => $rows->pluck('id')->all() === [$organisation->id]);
        $this->assertNotContains('Active', (new Application(['category' => 'Visa']))->statusOptions());
    }

    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
