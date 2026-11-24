<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use OTPHP\InternalClock;
use OTPHP\TOTP;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return response()->view('auth.login')->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        $request->session()->forget('pending_mfa');

        if (! Auth::once($credentials)) {
            return back()
                ->withErrors(['email' => 'The provided credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = $request->user();
        Auth::forgetUser();
        if (! $user->hasPortalAccess()) {
            return back()->withErrors(['email' => 'Your account access is restricted. Contact your administrator.'])->onlyInput('email');
        }

        if (config('auth.require_mfa')) {
            $request->session()->regenerate(true);
            $request->session()->put('pending_mfa', [
                'user_id' => $user->id,
                'version' => $user->authenticationVersion(),
                'expires_at' => now()->addMinutes(5)->timestamp,
                'secret' => $user->mfa_secret === null
                    ? Crypt::encryptString(TOTP::generate(new InternalClock(), 20)->getSecret())
                    : null,
            ]);

            return redirect()->route('login.mfa');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function challenge(Request $request): Response
    {
        $pending = $this->pendingMfa($request);
        if ($pending === null) {
            return redirect()->route('login');
        }

        return response()->view('auth.login', [
            'challenge' => true,
            'secret' => $pending['secret'] === null ? null : Crypt::decryptString($pending['secret']),
        ])->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pendingMfa($request);
        if ($pending === null) {
            return redirect()->route('login');
        }

        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $user = User::query()->find($pending['user_id']);
        if (! $user?->hasPortalAccess() || ! hash_equals($user->authenticationVersion(), $pending['version'])) {
            $request->session()->forget('pending_mfa');

            return redirect()->route('login');
        }

        $secret = $user->mfa_secret ?? Crypt::decryptString($pending['secret']);
        $totp = TOTP::createFromSecret($secret, new InternalClock());
        $step = intdiv(now()->timestamp, 30);
        foreach ([$step, $step - 1, $step + 1] as $candidate) {
            if (! $totp->verify($data['code'], $candidate * 30)) {
                continue;
            }

            // Compare against the same credentials and claim the step in one database write.
            $changes = ['mfa_last_step' => $candidate];
            if ($user->mfa_secret === null) {
                $changes['mfa_secret'] = $pending['secret'];
            }
            $claimed = User::query()->whereKey($user->id)
                ->where('password', $user->password)
                ->where('mfa_secret', $user->getRawOriginal('mfa_secret'))
                ->where(fn ($query) => $query->whereNull('mfa_last_step')->orWhere('mfa_last_step', '<', $candidate))
                ->update($changes);

            if ($claimed) {
                $password = $user->password;
                $storedSecret = $changes['mfa_secret'] ?? $user->getRawOriginal('mfa_secret');
                $user->refresh();
                $request->session()->forget('pending_mfa');
                if (! $user->hasPortalAccess() || $user->password !== $password || $user->getRawOriginal('mfa_secret') !== $storedSecret) {
                    return redirect()->route('login');
                }
                Auth::login($user);
                $request->session()->put('mfa_verified', $user->authenticationVersion());

                return redirect()->intended(route('home'));
            }

            break;
        }

        return back()->withErrors(['code' => 'Enter a current, unused code from your authenticator app.']);
    }

    private function pendingMfa(Request $request): ?array
    {
        $pending = $request->session()->get('pending_mfa');
        if (! config('auth.require_mfa') || ! $pending || $pending['expires_at'] <= now()->timestamp) {
            $request->session()->forget('pending_mfa');

            return null;
        }

        return $pending;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
