<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:prune')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('occurrences:prune')->dailyAt('03:45')->withoutOverlapping();
Schedule::command('privacy:prune')->dailyAt('04:00')->withoutOverlapping();
