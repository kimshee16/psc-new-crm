<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\StaffLeave;
use App\Models\User;
use App\Support\PSC\LeadAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_see_only_their_own_leave_even_when_requesting_staff_view(): void
    {
        $user = $this->staff();
        $own = $this->leave($user, ['reason' => 'My own holiday']);
        $this->leave($this->staff(), ['reason' => 'Other staff private leave']);

        $this->actingAs($user)->get('/holidays')->assertOk()
            ->assertSee('My own holiday')->assertDontSee('Other staff private leave')
            ->assertDontSee('Office staff leave')
            ->assertSee(route('holidays.edit', $own));
        $this->get('/holidays?view=staff&user_id=999&offices[]=SYD')->assertOk()
            ->assertViewHas('leaves', fn ($leaves) => $leaves->modelKeys() === [$own->id]);
        $this->get('/holidays/create')->assertOk()->assertSee('Save leave');
        $this->get('/holidays/'.$own->id.'/edit')->assertOk()->assertSee('My own holiday');
    }

    public function test_managers_can_view_staff_in_their_offices_but_cannot_change_staff_leave(): void
    {
        $manager = $this->staff(['account_type' => User::ACCOUNT_TYPE_MANAGER, 'offices' => ['MEL', 'SYD']]);
        $own = $this->leave($manager);
        $melbourne = $this->leave($this->staff());
        $sydney = $this->leave($this->staff(['offices' => ['SYD']]));
        $shared = $this->leave($this->staff(['offices' => ['JKT', 'MEL'], 'default_office_code' => 'JKT']));
        $outside = $this->leave($this->staff(['offices' => ['JKT']]), ['reason' => 'Outside office holiday']);

        $this->actingAs($manager)->get('/holidays')->assertOk()
            ->assertViewHas('leaves', fn ($leaves) => $leaves->modelKeys() === [$own->id]);
        $this->get('/holidays?view=staff&offices[]=JKT')->assertOk()
            ->assertDontSee('Outside office holiday')
            ->assertDontSee(route('holidays.edit', $melbourne))
            ->assertViewHas('leaves', fn ($leaves) => $leaves->modelKeys() === [$shared->id, $sydney->id, $melbourne->id, $own->id]);

        foreach ([$melbourne, $outside] as $leave) {
            $this->get('/holidays/'.$leave->id.'/edit')->assertForbidden();
            $this->patch('/holidays/'.$leave->id, $this->dates())->assertForbidden();
            $this->patch('/holidays/'.$leave->id.'/cancel')->assertForbidden();
            $this->assertTrue($leave->fresh()->active);
        }
    }

    public function test_managers_without_offices_see_only_their_own_leave_and_regional_access_tracks_changes(): void
    {
        $manager = $this->staff(['account_type' => User::ACCOUNT_TYPE_REGIONAL_MANAGER, 'offices' => []]);
        $own = $this->leave($manager);
        $staffLeave = $this->leave($this->staff());
        $region = Region::where('name', 'AUS')->firstOrFail();

        $this->actingAs($manager)->get('/holidays?view=staff')->assertOk()
            ->assertViewHas('leaves', fn ($leaves) => $leaves->modelKeys() === [$own->id]);
        $region->update(['regional_manager_id' => $manager->id]);
        $this->get('/holidays?view=staff')->assertOk()
            ->assertViewHas('leaves', fn ($leaves) => $leaves->modelKeys() === [$staffLeave->id, $own->id]);
        $region->update(['regional_manager_id' => null]);
        $this->get('/holidays?view=staff')->assertOk()
            ->assertViewHas('leaves', fn ($leaves) => $leaves->modelKeys() === [$own->id]);
    }

    public function test_record_update_and_cancel_use_authenticated_owner_and_preserve_history(): void
    {
        $user = $this->staff();
        $other = $this->staff();
        $this->actingAs($user)->post('/holidays', [
            ...$this->dates(), 'user_id' => $other->id, 'active' => false, 'reason' => 'Annual leave',
        ])->assertSessionHasNoErrors()->assertRedirect('/holidays');
        $leave = StaffLeave::sole();
        $this->assertSame($user->id, $leave->user_id);
        $this->assertTrue($leave->active);

        $this->patch('/holidays/'.$leave->id, [
            'starts_on' => '2026-11-01', 'ends_on' => '2026-11-01', 'reason' => '',
            'user_id' => $other->id, 'active' => false,
        ])->assertSessionHasNoErrors()->assertRedirect('/holidays');
        $this->assertDatabaseHas('staff_leaves', [
            'id' => $leave->id, 'user_id' => $user->id, 'reason' => null, 'active' => true,
        ]);
        $this->assertSame('2026-11-01', $leave->fresh()->starts_on->toDateString());
        $this->assertSame('2026-11-01', $leave->fresh()->ends_on->toDateString());
        $this->patch('/holidays/'.$leave->id.'/cancel')->assertRedirect('/holidays');
        $this->assertFalse($leave->fresh()->active);
        $this->get('/holidays')->assertOk()->assertSee('Cancelled')
            ->assertDontSee(route('holidays.edit', $leave));
        $this->patch('/holidays/'.$leave->id, $this->dates())->assertNotFound();
        $this->assertSame(1, StaffLeave::count());
    }

    public function test_invalid_dates_and_reasons_are_rejected_without_modifying_leave(): void
    {
        $user = $this->staff();
        $leave = $this->leave($user);
        $this->actingAs($user);
        foreach ([
            ['starts_on', '', 'starts_on'],
            ['starts_on', '2026-02-30', 'starts_on'],
            ['starts_on', 'tomorrow', 'starts_on'],
            ['ends_on', '', 'ends_on'],
            ['ends_on', '2026-10-19', 'ends_on'],
            ['reason', str_repeat('a', 161), 'reason'],
        ] as [$field, $value, $error]) {
            $data = array_replace($this->dates(), [$field => $value]);
            $this->from('/holidays/create')->post('/holidays', $data)
                ->assertRedirect('/holidays/create')->assertSessionHasErrors($error);
            $this->from('/holidays/'.$leave->id.'/edit')->patch('/holidays/'.$leave->id, $data)
                ->assertSessionHasErrors($error);
        }
        $this->get('/holidays/'.$leave->id.'/edit')->assertOk()->assertSee('The leave period was not saved.');
        $this->assertSame('2026-10-20', $leave->fresh()->starts_on->toDateString());
        $this->assertSame(1, StaffLeave::count());
    }

    public function test_guests_restricted_users_and_other_owners_cannot_access_leave_actions(): void
    {
        $owner = $this->staff();
        $leave = $this->leave($owner);
        foreach (['/holidays', '/holidays/create', '/holidays/'.$leave->id.'/edit'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/holidays', $this->dates())->assertRedirect('/login');
        $this->patch('/holidays/'.$leave->id, $this->dates())->assertRedirect('/login');
        $this->patch('/holidays/'.$leave->id.'/cancel')->assertRedirect('/login');

        $this->actingAs($this->staff());
        $this->get('/holidays/'.$leave->id.'/edit')->assertForbidden();
        $this->patch('/holidays/'.$leave->id, $this->dates())->assertForbidden();
        $this->patch('/holidays/'.$leave->id.'/cancel')->assertForbidden();
        $this->get('/holidays/999999/edit')->assertNotFound();
        $this->actingAs($this->staff(['portal_invite_status' => 'Restrict Access']))
            ->post('/holidays', $this->dates())->assertRedirect('/login');
        $this->assertSame(1, StaffLeave::count());
    }

    public function test_recorded_leave_excludes_both_boundary_dates_and_returns_user_afterwards(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->post('/holidays', $this->dates())->assertSessionHasNoErrors();
        $service = app(LeadAssignmentService::class);

        foreach ([
            ['2026-10-19 23:59:59', true, 'Upcoming'],
            ['2026-10-20 00:00:00', false, 'On leave'],
            ['2026-10-21 12:00:00', false, 'On leave'],
            ['2026-10-22 23:59:59', false, 'On leave'],
            ['2026-10-23 00:00:00', true, 'Completed'],
        ] as [$time, $eligible, $status]) {
            $this->travelTo(Carbon::parse($time, config('app.timezone')));
            $assignment = $service->assign('Australia', 'Melbourne');
            $this->assertSame($eligible ? $user->id : null, $assignment['user']?->id, $time);
            $this->assertSame($status, StaffLeave::sole()->status());
            if (! $eligible) {
                $this->assertSame('Melbourne Web Queue', $assignment['name']);
            }
        }
        $this->travelBack();
    }

    public function test_single_day_overlapping_leave_and_cancellation_control_allocation(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', config('app.timezone')));
        $user = $this->staff();
        $available = $this->staff();
        $this->actingAs($user)->post('/holidays', [
            'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20',
        ])->assertSessionHasNoErrors();
        $first = StaffLeave::sole();
        $this->post('/holidays', $this->dates())->assertSessionHasNoErrors();
        $second = StaffLeave::latest('id')->firstOrFail();
        $service = app(LeadAssignmentService::class);

        $this->assertSame($available->id, $service->assign('Australia', 'Melbourne')['user']?->id);
        $this->patch('/holidays/'.$first->id.'/cancel')->assertRedirect();
        $this->assertSame($available->id, $service->assign('Australia', 'Melbourne')['user']?->id);
        $this->patch('/holidays/'.$second->id.'/cancel')->assertRedirect();
        $this->assertSame($user->id, $service->assign('Australia', 'Melbourne')['user']?->id);
        $this->travelBack();
    }

    public function test_editing_leave_dates_immediately_changes_allocation_eligibility(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', config('app.timezone')));
        $user = $this->staff();
        $leave = $this->leave($user);
        $service = app(LeadAssignmentService::class);
        $this->assertNull($service->assign('Australia', 'Melbourne')['user']);
        $this->actingAs($user)->patch('/holidays/'.$leave->id, [
            'starts_on' => '2026-11-01', 'ends_on' => '2026-11-02',
        ])->assertSessionHasNoErrors();
        $this->assertSame($user->id, $service->assign('Australia', 'Melbourne')['user']?->id);
        $this->travelBack();
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create(array_replace([
            'account_type' => 'Counsellor', 'offices' => ['MEL'], 'include_in_round_robin' => true,
        ], $attributes));
    }

    private function leave(User $user, array $attributes = []): StaffLeave
    {
        return $user->staffLeaves()->create(array_replace($this->dates(), $attributes));
    }

    private function dates(): array
    {
        return ['starts_on' => '2026-10-20', 'ends_on' => '2026-10-22'];
    }
}
