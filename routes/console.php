<?php

use App\Jobs\SyncRoster;
use App\Jobs\SyncStatsimSessions;
use App\Jobs\UpdateOnlineControllers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncRoster)->everyTwoHours();

Schedule::job(new UpdateOnlineControllers)->everyMinute();

// The current and previous month are both re-synced daily, because Statsim can
// backfill sessions after the month has rolled over.
Schedule::call(function () {
    $now = Carbon::now();
    $prev = $now->copy()->subMonthNoOverflow();

    SyncStatsimSessions::dispatch($now->year, $now->month);
    SyncStatsimSessions::dispatch($prev->year, $prev->month);

    Log::info('Queued scheduled Statsim sync', [
        'months' => [
            $now->format('Y-m'),
            $prev->format('Y-m'),
        ],
    ]);
})->dailyAt('04:00')->name('statsim-sync')->onFailure(function () {
    Log::error('Scheduled Statsim sync failed to dispatch');
});
