<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

Schedule::command('requests:automate')->everyFifteenMinutes()->withoutOverlapping();
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('askonce:backup')->dailyAt('02:00')->when(fn () => config('app.backups_enabled'))->withoutOverlapping();
