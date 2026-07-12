<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rescue meetings whose job was lost (dead worker, dropped connection) —
// without this they sit in `processing` forever and retry is blocked by
// the ShouldBeUnique lock. Requires `php artisan schedule:work` (dev) or
// a system cron hitting `schedule:run` (production).
Schedule::command('meetings:fail-stuck')->everyTenMinutes();
