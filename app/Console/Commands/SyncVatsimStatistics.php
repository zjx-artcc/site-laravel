<?php

namespace App\Console\Commands;

use App\Http\Controllers\StatisticsController;
use App\Jobs\SyncVatsimSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncVatsimStatistics extends Command
{
    protected $signature = 'statistics:sync {from? : UTC start date or datetime} {to? : UTC end date or datetime}';

    protected $description = 'Synchronize controller statistics from the VATSIM Core API';

    public function handle(): int
    {
        try {
            $from = $this->argument('from')
                ? Carbon::parse($this->argument('from'))->utc()
                : Carbon::now()->subDays(StatisticsController::DEFAULT_LOOKBACK_DAYS)->startOfDay();
            $to = $this->argument('to')
                ? Carbon::parse($this->argument('to'))->utc()
                : Carbon::now()->endOfDay();
        } catch (\Throwable) {
            $this->error('Dates must be valid UTC dates or datetimes.');

            return self::FAILURE;
        }

        if ($from->greaterThan($to)) {
            $this->error('The start date must be before or equal to the end date.');

            return self::FAILURE;
        }

        SyncVatsimSessions::dispatch($from->toIso8601String(), $to->toIso8601String());
        $this->info("Queued VATSIM Core statistics sync from {$from->toIso8601String()} to {$to->toIso8601String()}.");

        return self::SUCCESS;
    }
}
