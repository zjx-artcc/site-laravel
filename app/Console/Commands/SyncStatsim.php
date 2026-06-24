<?php

namespace App\Console\Commands;

use App\Jobs\SyncStatsimSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncStatsim extends Command
{
    protected $signature = 'statsim:sync {year : Year to start from} {month : Month to start from (1-12)} {end_month? : Optional end month to sync a range (1-12)}';
    protected $description = 'Sync controller statistics from Statsim for a specific month or range of months';

    public function handle(): void
    {
        $year       = (int) $this->argument('year');
        $startMonth = (int) $this->argument('month');
        $endMonth   = $this->argument('end_month') !== null ? (int) $this->argument('end_month') : $startMonth;

        if ($startMonth < 1 || $startMonth > 12 || $endMonth < 1 || $endMonth > 12) {
            $this->error('Month must be between 1 and 12.');
            return;
        }

        if ($endMonth < $startMonth) {
            $this->error('End month must be greater than or equal to start month.');
            return;
        }

        $cursor = Carbon::createFromDate($year, $startMonth, 1);
        $end    = Carbon::createFromDate($year, $endMonth, 1);
        $total  = $endMonth - $startMonth + 1;

        $this->info("Syncing {$total} month(s) from {$cursor->format('M Y')} to {$end->format('M Y')}...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        while ($cursor->lessThanOrEqualTo($end)) {
            (new SyncStatsimSessions($cursor->year, $cursor->month))->handle();
            $bar->advance();
            $cursor->addMonthNoOverflow();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sync complete.');
    }
}
