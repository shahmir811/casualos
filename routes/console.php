<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| SCHEDULED TASKS
|--------------------------------------------------------------------------
|
| To activate the Laravel scheduler, add ONE entry to your server's crontab:
|
|   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
|
| Laravel will then dispatch each task below according to its own schedule.
|
*/

// Backup every 6 hours, auto-prune backups older than 30 days
Schedule::command('backup:database --prune=30')
    ->everySixHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Auto-calculate weekly wages every Friday at 23:45
// Sums kameez returned per catalogue per stitching unit for the Saturday→Friday window.
Schedule::command('wages:calculate-weekly')
    ->weeklyOn(5, '23:45')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wages.log'));

// Auto-calculate weekly Tarpai charges every Friday at 23:50
// Sums pieces sent to Rashid Bhai and Yousaf Bhai for the Saturday→Friday window.
Schedule::command('tarpai:calculate-weekly')
    ->weeklyOn(5, '23:50')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/tarpai-charges.log'));

// Prune audit log entries older than 45 days — runs on the first Sunday of every month at 00:00.
// Cron expression 0 0 1-7 * 0 fires at midnight on days 1–7 of the month only when it is a Sunday.
Schedule::command('audit-log:prune')
    ->cron('0 0 1-7 * 0')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/audit-log-prune.log'));

// Prune S3 backup files older than 30 days — runs on the first Sunday of every month at 00:05.
// Offset by 5 minutes from audit-log:prune to avoid overlapping log writes.
Schedule::command('backups:prune')
    ->cron('5 0 1-7 * 0')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backups-prune.log'));
