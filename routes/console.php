<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::useCache('database');
Schedule::command('store:reconcile-payments --limit=25')->everyFifteenMinutes()->withoutOverlapping(5);
Schedule::command('store:retry-notifications --limit=25')->everyFifteenMinutes()->withoutOverlapping(5);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
