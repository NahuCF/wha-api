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

// Check for bot sessions about to expire and send warnings
Schedule::command('bot:check-warnings')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Check for expired subscriptions and update their status
Schedule::command('subscriptions:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
