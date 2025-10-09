<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Process scheduled broadcasts every minute
Schedule::command('broadcasts:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Clean up old bot versions daily
Schedule::command('bot:cleanup-versions --days=30')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping();
