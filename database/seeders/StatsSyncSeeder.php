<?php

namespace Database\Seeders;

use App\Jobs\SyncVatsimSessions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class StatsSyncSeeder extends Seeder
{
    private const BACKFILL_MONTHS = 1;

    public function run(): void
    {
        $cursor = Carbon::now()->subMonthsNoOverflow(self::BACKFILL_MONTHS - 1)->startOfMonth();
        $end = Carbon::now()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            SyncVatsimSessions::dispatch($cursor->year, $cursor->month);
            $cursor->addMonthNoOverflow();
        }
    }
}
