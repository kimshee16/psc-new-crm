<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use OTPHP\InternalClock;
use OTPHP\TOTP;
use Tests\TestCase;

class CrmMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolment_requires_an_authenticator_and_subsequent_sign_ins_reject_replayed_codes(): void
    {
        config(['auth.require_mfa' => true]);
        $this->freezeTime();
        $user = User::factory()->create(['account_type' => 'Counsellor']);
        $credentials = ['email' => $user->email, 'password' => 'password', 'remember' => '1'];

        $this->get('/clients')->assertRedirect('/login');
        $this->post('/login', $credentials)->assertRedirect('/login/verify');
        $this->assertGuest();
        $this->get('/clients')->assertRedirect('/login');
        $secret = Crypt::decryptString(session('pending_mfa.secret'));
        $this->get('/login/verify')->assertOk()->assertSee($secret);
        $totp = TOTP::createFromSecret($secret, new InternalClock());
        $code = $totp->at(now()->timestamp);
        $this->from('/login/verify')->post('/login/verify', ['code' => 'invalid'])->assertSessionHasErrors('code');
        $this->post('/login/verify', ['code' => $code])->assertRedirect('/clients');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($secret, $user->fresh()->getRawOriginal('mfa_secret'));
        $this->get('/configuration')->assertForbidden();

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', $credentials)->assertRedirect('/login/verify');
        $this->get('/login/verify')->assertOk()->assertDontSee($secret);
        $this->from('/login/verify')->post('/login/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->travel(30)->seconds();
        $this->post('/login/verify', ['code' => $totp->at(now()->timestamp)])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_sessions_and_expired_or_revoked_challenges_require_a_fresh_password(): void
    {
        config(['auth.require_mfa' => true]);
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'password'];
        $this->actingAs($user)->getJson('/clients')->assertUnauthorized();
        $this->post('/login', $credentials)->assertRedirect('/login/verify');
        $this->travel(5)->minutes();
        $this->get('/login/verify')->assertRedirect('/login');
        $this->post('/login', $credentials)->assertRedirect('/login/verify');
        $user->update(['password' => 'ChangedPassword123!']);
        $this->post('/login/verify', ['code' => '123456'])->assertRedirect('/login');
        $this->assertGuest();
    }
}
