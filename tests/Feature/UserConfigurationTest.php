<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserInvitation;
use App\Support\PSC\LeadAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_sorted_and_open_add_and_edit_forms(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Zoe Sample', 'account_type' => 'Counsellor']);
        $this->actingAs($admin)->get('/configuration?tab=users')->assertOk()
            ->assertSee('Save Changes')->assertSee('Remove Changes')->assertSee('Add User')
            ->assertSeeInOrder(['Name', 'Email', 'User Role', 'User Picture', 'User Status', 'Office', 'Default Office', 'User Round Robin Status', 'User ID', 'Portal Invite Status']);
        $this->get('/configuration?tab=users&sort=name&direction=desc')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->first()->id === $user->id);
        $this->get('/configuration?tab=users&action=create')->assertOk()
            ->assertSee('Submit and Exit')->assertSee('First Name')->assertSee('Last Name')->assertSee('Send Invite');
        $this->get('/configuration?tab=users&edit='.$user->id)->assertOk()->assertSee($user->email);
        $this->get('/configuration?tab=users&edit=999999')->assertNotFound();
    }

    public function test_creating_user_saves_profile_picture_and_sends_usable_single_use_invitation(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->actingAs($this->admin())->post('/configuration/users', $this->profile([
            'portal_invite_status' => 'Send Invite',
            'picture' => UploadedFile::fake()->image('portrait.png'),
        ]))->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=users');
        $user = User::where('email', 'jane@example.test')->firstOrFail();
        $this->assertSame('Jane Sample', $user->name);
        $this->assertSame(['MEL', 'SYD'], $user->offices);
        $this->assertSame('SYD', $user->default_office_code);
        $this->assertSame('Invite Sent', $user->portal_invite_status);
        $this->assertFalse(Hash::check('password', $user->password));
        Storage::disk('local')->assertExists($user->picture_path);
        $this->get('/configuration/users/'.$user->id.'/picture')->assertOk();

        $token = null;
        Notification::assertSentTo($user, UserInvitation::class, function ($notification) use (&$token, $user) {
            $token = $notification->token;
            $this->assertStringContainsString('/invitation/', $notification->toMail($user)->actionUrl);

            return true;
        });
        $this->post('/logout');
        $this->get(route('invitation.accept', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Set Password');
        $credentials = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->post('/invitation', $credentials)->assertSessionHasNoErrors()->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->post('/invitation', $credentials)->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPassword123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_profile_updates_preserve_password_and_replace_picture(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['picture_path' => 'user-pictures/old.png']);
        Storage::disk('local')->put($user->picture_path, 'old image');
        $oldPassword = $user->password;
        $this->actingAs($this->admin())->patch('/configuration/users/'.$user->id, $this->profile([
            'picture' => UploadedFile::fake()->image('replacement.png'),
        ]))->assertSessionHasNoErrors();
        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertSame('Jane Sample', $user->fresh()->name);
        Storage::disk('local')->assertMissing('user-pictures/old.png');
        Storage::disk('local')->assertExists($user->fresh()->picture_path);
    }

    public function test_bulk_save_is_atomic_accepts_shared_offices_and_ignores_extra_attributes(): void
    {
        Notification::fake();
        $first = User::factory()->create(['account_type' => 'Counsellor']);
        $second = User::factory()->create(['account_type' => 'Counsellor']);
        $this->actingAs($this->admin());
        $rows = [
            $first->id => $this->access(['id' => $first->id, 'account_type' => 'Manager', 'password' => 'injected', 'email' => 'injected@example.test']),
            $second->id => $this->access(['id' => $second->id, 'default_office_code' => 'JKT']),
        ];
        $this->patch('/configuration/users', ['users' => $rows])->assertSessionHasErrors('users.'.$second->id.'.default_office_code');
        $this->assertSame('Counsellor', $first->fresh()->account_type);
        Notification::assertNothingSent();
        $rows[$second->id]['default_office_code'] = 'MEL';
        $this->patch('/configuration/users', ['users' => $rows])->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=users');
        $this->assertSame('Manager', $first->fresh()->account_type);
        $this->assertSame('SYD', $first->fresh()->default_office_code);
        $this->assertSame('MEL', $second->fresh()->default_office_code);
        $this->assertSame($first->email, $first->fresh()->email);
        $this->assertSame($first->password, $first->fresh()->password);
    }

    public function test_invalid_user_fields_and_default_outside_selected_offices_are_rejected(): void
    {
        $this->actingAs($admin = $this->admin());
        $url = '/configuration?tab=users&action=create';
        foreach ([
            ['first_name', '', 'first_name'], ['last_name', '', 'last_name'],
            ['email', $admin->email, 'email'], ['email', 'invalid', 'email'],
            ['account_type', 'Unknown', 'account_type'], ['is_active', 'maybe', 'is_active'],
            ['offices', [], 'offices'], ['offices', ['UNKNOWN'], 'offices.0'],
            ['offices', ['MEL', 'MEL'], 'offices'], ['default_office_code', 'JKT', 'default_office_code'],
            ['include_in_round_robin', 'maybe', 'include_in_round_robin'],
            ['portal_invite_status', 'unknown', 'portal_invite_status'],
            ['phone_country_code', '61', 'phone_country_code'], ['phone_number', 'abc', 'phone_number'],
            ['picture', UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'), 'picture'],
        ] as [$field, $value, $error]) {
            $this->from($url)->post('/configuration/users', $this->profile([$field => $value]))->assertRedirect($url)->assertSessionHasErrors($error);
        }
        $this->assertSame(1, User::count());
        $this->get($url)->assertOk()->assertSee('Jane')->assertSee('The configuration was not saved.');
    }

    public function test_only_administrators_can_manage_users_and_cannot_remove_their_own_access(): void
    {
        $admin = $this->admin();
        $this->post('/configuration/users', $this->profile())->assertRedirect('/login');
        foreach (['Counsellor', 'Manager', User::ACCOUNT_TYPE_REGIONAL_MANAGER] as $role) {
            $this->actingAs(User::factory()->create(['account_type' => $role]));
            $this->get('/configuration?tab=users')->assertForbidden();
            $this->post('/configuration/users', $this->profile())->assertForbidden();
            $this->patch('/configuration/users/'.$admin->id, $this->profile())->assertForbidden();
            $this->patch('/configuration/users', ['users' => [$this->access(['id' => $admin->id])]])->assertForbidden();
            $this->get('/configuration/users/'.$admin->id.'/picture')->assertForbidden();
        }
        $this->actingAs($admin);
        foreach ([['account_type' => 'Counsellor'], ['is_active' => 0], ['portal_invite_status' => 'Restrict Access']] as $change) {
            $this->patch('/configuration/users', ['users' => [$this->access(array_replace(['id' => $admin->id, 'account_type' => 'Admin'], $change))]])->assertSessionHasErrors();
        }
        $this->assertTrue($admin->fresh()->hasPortalAccess());
        $this->assertSame('Admin', $admin->fresh()->account_type);
    }

    public function test_round_robin_uses_default_office_and_skips_inactive_restricted_and_excluded_users(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['account_type' => 'Counsellor', 'offices' => ['MEL', 'SYD'], 'default_office_code' => 'SYD']);
        $service = app(LeadAssignmentService::class);
        $this->assertSame($user->id, $service->assign('Australia', 'Australia - Sydney')['user']?->id);
        $this->assertNull($service->assign('Australia', 'Australia - Melbourne')['user']);
        foreach ([['is_active' => 0], ['portal_invite_status' => 'Restrict Access'], ['include_in_round_robin' => 0]] as $change) {
            $this->actingAs($admin)->patch('/configuration/users', ['users' => [$this->access(['id' => $user->id] + $change)]])->assertSessionHasNoErrors();
            $this->assertNull($service->assign('Australia', 'Australia - Sydney')['user']);
        }
        $this->patch('/configuration/users', ['users' => [$this->access(['id' => $user->id, 'default_office_code' => 'MEL'])]])->assertSessionHasNoErrors();
        $this->assertNull($service->assign('Australia', 'Australia - Sydney')['user']);
        $this->assertSame($user->id, $service->assign('Australia', 'Australia - Melbourne')['user']?->id);
    }

    public function test_inactive_and_restricted_users_cannot_login_or_continue_existing_sessions(): void
    {
        foreach ([['is_active' => false], ['portal_invite_status' => 'Restrict Access']] as $attributes) {
            $user = User::factory()->create($attributes);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->actingAs($user)->get('/')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_expired_and_restricted_invitation_links_cannot_set_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $credentials = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $user->update(['portal_invite_status' => 'Restrict Access']);
        $this->post('/invitation', $credentials)->assertSessionHasErrors('email');
        $user->update(['portal_invite_status' => 'Invite Sent']);
        $this->travel(61)->minutes();
        $this->post('/invitation', $credentials)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    private function admin(): User
    {
        return User::factory()->create(['name' => 'Admin User', 'account_type' => 'Admin', 'include_in_round_robin' => false]);
    }

    private function access(array $overrides = []): array
    {
        return array_replace([
            'account_type' => 'Counsellor', 'is_active' => 1, 'include_in_round_robin' => 1,
            'offices' => ['MEL', 'SYD'], 'default_office_code' => 'SYD', 'portal_invite_status' => 'Invite Sent',
        ], $overrides);
    }

    private function profile(array $overrides = []): array
    {
        return array_replace($this->access() + [
            'first_name' => 'Jane', 'last_name' => 'Sample', 'email' => 'jane@example.test',
            'phone_country_code' => '+61', 'phone_number' => '412 345 678',
        ], $overrides);
    }
}
