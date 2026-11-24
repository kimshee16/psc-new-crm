<?php

use App\Models\User;
use App\Support\PSC\SbsRecord;
use App\Support\PSC\VisaGrants;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('crm:reset-mfa {email}', function () {
    $user = User::query()->where('email', $this->argument('email'))->firstOrFail();
    if (! $this->confirm("Reset authentication for {$user->email} after verifying their identity?")) {
        return 1;
    }

    DB::transaction(function () use ($user): void {
        $user->forceFill([
            'password' => Str::random(48),
            'remember_token' => Str::random(60),
            'mfa_secret' => null,
            'mfa_last_step' => null,
        ])->save();
        Password::deleteToken($user);
    });

    $this->info('Authentication reset. Send a fresh invitation from Configuration → Users.');
})->purpose('Reset a CRM user’s password and authenticator after identity verification');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('visas:apply-grants', function (VisaGrants $grants) {
    $this->info('Updated '.$grants->applyDue().' client visa record(s).');
})->purpose('Apply granted visas whose effective date has arrived');

Schedule::command('visas:apply-grants')->everyMinute()->withoutOverlapping();

Artisan::command('sbs:expire', function (SbsRecord $records) {
    $this->info('Expired '.$records->expireDue().' sponsorship record(s).');
})->purpose('Expire SBS and Labour Agreement records when their expiry date is reached');

Schedule::command('sbs:expire')->everyMinute()->withoutOverlapping();
