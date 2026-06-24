<?php

use App\Jobs\SyncRoster;
use App\Jobs\SyncStatsimSessions;
use App\Jobs\UpdateOnlineControllers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncRoster())->everyTwoHours();

Schedule::job(new UpdateOnlineControllers())->everyMinute();

Schedule::call(function () {
    $now = Carbon::now();
    SyncStatsimSessions::dispatch($now->year, $now->month);
    $prev = $now->copy()->subMonthNoOverflow();
    SyncStatsimSessions::dispatch($prev->year, $prev->month);
})->dailyAt('04:00');