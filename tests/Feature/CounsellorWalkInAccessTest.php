<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounsellorWalkInAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_discover_their_own_and_other_active_staff_form_links(): void
    {
        $viewer = User::factory()->create(['name' => 'Zoe Counsellor', 'account_type' => 'Counsellor']);
        $other = User::factory()->create(['name' => 'Ana Counsellor']);
        $inactive = User::factory()->create(['name' => 'Inactive Staff', 'is_active' => false]);
        $restricted = User::factory()->create(['name' => 'Restricted Staff', 'portal_invite_status' => 'Restrict Access']);

        $this->actingAs($viewer)->get(route('home'))->assertOk()
            ->assertSee(route('counsellor-intake.index'), false)->assertSee('Walk-in Forms');
        $this->get(route('counsellor-intake.index'))->assertOk()
            ->assertSeeInOrder(['Zoe Counsellor', 'Ana Counsellor'])
            ->assertSee('Your form')->assertSee('Open form')->assertSee('Copy link')
            ->assertSee(route('counsellor-intake.show', $viewer), false)
            ->assertSee(route('counsellor-intake.show', $other), false)
            ->assertDontSee('Inactive Staff')->assertDontSee('Restricted Staff')
            ->assertDontSee(route('counsellor-intake.show', $inactive).'"', false)
            ->assertDontSee(route('counsellor-intake.show', $restricted).'"', false);
    }

    public function test_directory_requires_login_but_shared_form_remains_public(): void
    {
        $counsellor = User::factory()->create(['name' => 'Priya Raman']);

        $this->get(route('counsellor-intake.index'))->assertRedirect(route('login'));
        $this->get(route('counsellor-intake.show', $counsellor))->assertOk()
            ->assertSee('Counsellor: Priya')->assertSee('Please fill in your details to get started.');
    }

    public function test_restricted_staff_cannot_access_the_directory(): void
    {
        $user = User::factory()->create(['portal_invite_status' => 'Restrict Access']);

        $this->actingAs($user)->get(route('counsellor-intake.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
