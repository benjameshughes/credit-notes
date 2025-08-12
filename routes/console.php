<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up pending jobs every 30 minutes
Schedule::command('jobs:cleanup-pending')->everyThirtyMinutes();

// Clean up completed jobs hourly (only if ZIP files are older than 24 hours)
Schedule::command('jobs:cleanup-completed')->hourly();

// Clean up old files daily at 2 AM (keep files for 30 days)
Schedule::command('files:cleanup-old --days=30')->dailyAt('02:00');
