<?php

namespace App\Providers;

use App\Support\PSC\CrmAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CrmAccess::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('crm-login', function (Request $request) {
            $email = $request->input('email');

            return [
                Limit::perMinute(30)->by('ip:'.$request->ip()),
                Limit::perMinute(10)->by('email:'.(is_string($email) ? Str::lower(trim($email)) : $request->ip())),
            ];
        });

        RateLimiter::for('crm-mfa', fn (Request $request) => [
            Limit::perMinute(30)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('account:'.$request->session()->get('pending_mfa.user_id', 'guest:'.$request->ip())),
        ]);
    }
}
