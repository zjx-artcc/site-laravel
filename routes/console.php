<?php

use App\Jobs\ArchiveEvents;
use App\Jobs\ExpireLoas;
use App\Jobs\SyncRoster;
use App\Jobs\SyncVatsimSessions;
use App\Jobs\UpdateOnlineControllers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncRoster)->everyTwoHours();

Schedule::job(new UpdateOnlineControllers)->everyMinute();

Schedule::job(new ExpireLoas)->daily();

Schedule::job(new ArchiveEvents)->everyFiveMinutes();

Schedule::call(function () {
    $now = Carbon::now();
    SyncVatsimSessions::dispatch($now->year, $now->month);
    $prev = $now->copy()->subMonthNoOverflow();
    SyncVatsimSessions::dispatch($prev->year, $prev->month);
})->dailyAt('04:00');
