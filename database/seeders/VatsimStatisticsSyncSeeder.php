<?php

namespace Database\Seeders;

use App\Http\Controllers\StatisticsController;
use App\Jobs\SyncVatsimSessions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VatsimStatisticsSyncSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        SyncVatsimSessions::dispatch(
            $now->copy()->subDays(StatisticsController::DEFAULT_LOOKBACK_DAYS)->startOfDay()->toIso8601String(),
            $now->endOfDay()->toIso8601String(),
        );
    }
}
