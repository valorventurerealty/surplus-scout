<?php

use Illuminate\Foundation\Inspiring;
use App\Jobs\ImportGoogleCalendarEventsJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Stellar shared hosting cannot run a persistent worker. This drains queued work
// on each permitted five-minute cron tick and transitions cleanly to a persistent
// worker when the application moves to a VPS or managed cloud runtime.
Schedule::job(new ImportGoogleCalendarEventsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping(4);

Schedule::command('queue:work --stop-when-empty --tries=3 --timeout=90')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('tasks:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('properties:prune-intakes')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('contacts:prune-intakes')
    ->dailyAt('03:45')
    ->withoutOverlapping();

Schedule::command('surplus:prune-intakes')
    ->dailyAt('04:00')
    ->withoutOverlapping();

Schedule::command('email:prune-deleted-drafts')
    ->dailyAt('04:15')
    ->withoutOverlapping();

Schedule::command('armory:prune-deleted-sessions')
    ->dailyAt('04:30')
    ->withoutOverlapping();
