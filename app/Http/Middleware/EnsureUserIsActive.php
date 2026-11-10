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
            // Keep records, lists and nomination defaults current even between scheduler runs.
            app(\App\Support\PSC\SbsRecord::class)->expireDue();
        }

        return $next($request);
    }
}
