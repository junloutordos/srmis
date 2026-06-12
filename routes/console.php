<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/
Schedule::command('db:backup')->dailyAt('06:00');
Schedule::command('db:backup')->dailyAt('12:00');
Schedule::command('db:backup')->dailyAt('17:00');

// ── Log health check: disk, file sizes, error spikes ─────────────────────
Schedule::command('log:health')->dailyAt('06:00');

// ── Backup recency check: was a backup uploaded in the last 25h? ──────────
Schedule::command('backup:verify')->dailyAt('06:30')->withoutOverlapping();
