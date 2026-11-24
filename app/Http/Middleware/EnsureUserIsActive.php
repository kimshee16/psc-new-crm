<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasPortalAccess()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account access is restricted. Contact your administrator.']);
        }

        if ($request->user()) {
            if (config('auth.require_mfa') && ! hash_equals(
                $request->user()->authenticationVersion(),
                (string) $request->session()->get('mfa_verified'),
            )) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Sign in with two-step verification.'], 401)
                    : redirect()->guest(route('login'));
            }

            // Keep records, lists and nomination defaults current even between scheduler runs.
            app(\App\Support\PSC\SbsRecord::class)->expireDue();
        }

        return $next($request);
    }
}
