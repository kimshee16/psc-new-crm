<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.accept-invitation', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);
        $status = Password::reset($data, function (User $user, string $password) {
            if (! $user->hasPortalAccess()) {
                throw ValidationException::withMessages(['email' => 'Your account access is restricted. Contact your administrator.']);
            }
            $user->forceFill([
                'password' => $password, 'remember_token' => Str::random(60),
                'email_verified_at' => now(),
            ])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This invitation is invalid or has expired. Ask your administrator for a new invitation.'])->onlyInput('email');
        }

        return redirect()->route('login')->with('status', 'Your password is set. You can now sign in.');
    }
}
