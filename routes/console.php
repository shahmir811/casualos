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
