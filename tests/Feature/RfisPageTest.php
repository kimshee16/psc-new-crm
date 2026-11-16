<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationRfi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RfisPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_visibility_combines_officer_assignment_and_all_three_application_roles_without_duplicates(): void
    {
        $viewer = User::factory()->create(['account_type' => 'Counsellor']);
        $other = User::factory()->create();
        $expected = [];
        foreach (['primary_counsellor_id', 'secondary_counsellor_id', 'migration_agent_id'] as $role) {
            $application = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $other->id, $role => $viewer->id]);
            $expected[] = $this->rfi($application, $other)->id;
        }
        $unrelated = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $other->id]);
        $expected[] = $this->rfi($unrelated, $viewer)->id;
        $hidden = $this->rfi($unrelated, $other);
        $both = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $viewer->id]);
        $expected[] = $this->rfi($both, $viewer)->id;

        $this->actingAs($viewer)->get(route('rfis'))->assertOk()->assertSee('Display Closed RFIs')
            ->assertViewHas('rfis', fn ($rows) => $rows->total() === 5 && $rows->pluck('id')->sort()->values()->all() === collect($expected)->sort()->values()->all())
            ->assertDontSee('data-rfi-row="'.$hidden->id.'"', false);
        $this->get(route('rfis.edit', $expected[3]))->assertOk();
        $this->get(route('applications.show', $unrelated))->assertNotFound();
        $this->actingAs(User::factory()->create(['account_type' => 'Super Admin']))->get(route('rfis'))
            ->assertViewHas('rfis', fn ($rows) => $rows->total() === 0);
    }

    public function test_closed_visibility_resets_on_a_fresh_page_open(): void
    {
        $user = User::factory()->create();
        $application = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $user->id]);
        $this->rfi($application, $user);
        $closed = $this->rfi($application, $user, ['status' => 'Closed']);
        $this->actingAs($user)->get(route('rfis', ['display_closed' => 1]))->assertViewHas('rfis', fn ($rows) => $rows->total() === 2);
        $this->get(route('rfis'))->assertViewHas('rfis', fn ($rows) => $rows->total() === 1)
            ->assertDontSee('data-rfi-row="'.$closed->id.'"', false);
        $this->get(route('rfis', ['status' => ['Closed']]))->assertViewHas('rfis', fn ($rows) => $rows->total() === 0);
    }

    public function test_all_filters_combine_and_date_boundaries_are_inclusive(): void
    {
        $user = User::factory()->create();
        $officer = User::factory()->create();
        $application = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $user->id]);
        $match = $this->rfi($application, $officer, ['request_type' => 'Biometrics', 'status' => 'In preparation',
            'received_date' => '2026-11-16', 'due_date' => '2026-12-01', 'lodged_date' => '2026-11-20']);
        $this->rfi($application, $user, ['request_type' => 'Interview']);
        $this->rfi($application, $officer, ['request_type' => 'Biometrics', 'status' => 'Closed']);
        $filters = ['application_id' => str_pad((string) $application->id, 5, '0', STR_PAD_LEFT),
            'request_type' => ['Biometrics', 'Health or character'], 'status' => ['In preparation', 'Awaiting client'],
            'assigned_to_user_id' => [$officer->id], 'received_date_from' => '2026-11-16', 'received_date_to' => '2026-11-16',
            'due_date_from' => '2026-12-01', 'due_date_to' => '2026-12-01', 'lodged_date_from' => '2026-11-20', 'lodged_date_to' => '2026-11-20'];
        $this->actingAs($user)->get(route('rfis', $filters))->assertOk()
            ->assertViewHas('rfis', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $match->id);
        foreach (['received_date_from' => '2026-11-17', 'due_date_to' => '2026-11-30', 'lodged_date_to' => '2026-11-19'] as $key => $value) {
            $adjusted = array_replace($filters, [$key => $value]);
            if ($key === 'received_date_from') {
                $adjusted['received_date_to'] = '2026-11-18';
            }
            unset($adjusted['due_date_from'], $adjusted['lodged_date_from']);
            $this->get(route('rfis', $adjusted))->assertViewHas('rfis', fn ($rows) => $rows->total() === 0);
        }
        $this->get(route('rfis', ['application_id' => $application->record_reference]))->assertViewHas('rfis', fn ($rows) => $rows->total() === 2);
        $this->get(route('rfis', ['application_id' => '%']))->assertViewHas('rfis', fn ($rows) => $rows->total() === 0);
    }

    public function test_sorting_and_pagination_are_stable_and_preserve_filters(): void
    {
        $user = User::factory()->create(['name' => 'Zoe']);
        $officer = User::factory()->create(['name' => 'Ana']);
        $application = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $user->id]);
        for ($i = 1; $i <= 12; $i++) {
            $this->rfi($application, $i === 1 ? $officer : $user, [
                'request_type' => $i === 1 ? 'Biometrics' : 'Interview',
                'received_date' => sprintf('2026-11-%02d', $i), 'due_date' => sprintf('2026-12-%02d', $i),
                'lodged_date' => sprintf('2026-12-%02d', $i),
            ]);
        }
        $this->actingAs($user)->get(route('rfis', ['sort' => 'due_date', 'direction' => 'desc', 'per_page' => 10]))
            ->assertViewHas('rfis', fn ($rows) => $rows->total() === 12 && $rows->count() === 10 && $rows->first()->due_date->format('d') === '12');
        $this->get(route('rfis', ['sort' => 'due_date', 'direction' => 'desc', 'per_page' => 10, 'page' => 2]))
            ->assertViewHas('rfis', fn ($rows) => $rows->count() === 2 && $rows->first()->due_date->format('d') === '02');
        foreach (['request_type', 'received_date', 'lodged_date', 'officer'] as $sort) {
            $this->get(route('rfis', ['sort' => $sort, 'direction' => 'asc', 'per_page' => 20]))->assertOk()
                ->assertViewHas('rfis', fn ($rows) => $rows->count() === 12 && $rows->first()->officer->name === 'Ana');
        }
        $this->get(route('rfis', ['request_type' => ['Biometrics'], 'sort' => 'status']))
            ->assertViewHas('rfis', fn ($rows) => $rows->total() === 1);
    }

    public function test_csv_exports_only_filtered_visible_records_with_safe_cells_and_requested_filename(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $application = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $user->id]);
        $this->rfi($application, $user, ['request_type' => 'Other', 'request_type_other' => '=HYPERLINK("bad")',
            'request_letter_name' => 'request.pdf', 'proof_of_lodgement_name' => 'proof.pdf', 'notes' => "+SUM(1,2)\nSecond line"]);
        $this->rfi($application, $user, ['status' => 'Closed']);
        $hidden = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $other->id]);
        $this->rfi($hidden, $other, ['request_type' => 'Other', 'request_type_other' => 'Hidden request']);
        $this->travelTo(Carbon::parse('2026-11-16 10:30:45', 'Asia/Manila'));
        $response = $this->actingAs($user)->get(route('rfis.export', ['request_type' => ['Other']]))->assertOk()
            ->assertDownload('RFI list - 20261116 - 10:30:45.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+SUM", $csv);
        $this->assertStringContainsString('request.pdf', $csv);
        $this->assertStringNotContainsString('Hidden request', $csv);
        $this->assertStringNotContainsString('rfis/request.pdf', $csv);
        $this->assertStringNotContainsString('Closed', $csv);
        $this->travelBack();
    }

    public function test_invalid_filters_cannot_change_sort_sql_or_access(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['sort' => 'notes'], ['direction' => 'DROP TABLE'], ['request_type' => 'Biometrics'],
            ['request_type' => ['Bad']], ['status' => ['Bad']], ['assigned_to_user_id' => ['bad']],
            ['received_date_from' => 'bad'], ['due_date_from' => '2026-12-10', 'due_date_to' => '2026-12-01'],
            ['display_closed' => 'yes'], ['per_page' => 1000]] as $filters) {
            $this->getJson(route('rfis', $filters))->assertUnprocessable();
        }
        $this->assertDatabaseCount('application_activities', 0);
    }

    private function rfi(Application $application, User $officer, array $overrides = []): ApplicationRfi
    {
        return $application->rfis()->create(array_replace(['request_type' => 'RFI (s56 notice)', 'status' => 'Received',
            'assigned_to_user_id' => $officer->id, 'received_date' => '2026-11-16', 'due_date' => '2026-12-01',
            'request_letter_path' => 'rfis/request.pdf'], $overrides));
    }
}
