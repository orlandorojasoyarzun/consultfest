<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('festivals:sync')
    ->dailyAt('06:00')
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

Schedule::command('festivals:check-deadlines')
    ->dailyAt('08:00')
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();
