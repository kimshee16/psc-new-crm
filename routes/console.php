<?php

use App\Support\PSC\VisaGrants;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('visas:apply-grants', function (VisaGrants $grants) {
    $this->info('Updated '.$grants->applyDue().' client visa record(s).');
})->purpose('Apply granted visas whose effective date has arrived');

Schedule::command('visas:apply-grants')->everyMinute()->withoutOverlapping();

Artisan::command('sbs:expire', function (\App\Support\PSC\SbsRecord $records) {
    $this->info('Expired '.$records->expireDue().' SBS record(s).');
})->purpose('Expire SBS records when their expiry date is reached');

Schedule::command('sbs:expire')->everyMinute()->withoutOverlapping();
