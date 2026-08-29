<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncVatsimSessions implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $year, public int $month) {}

    public function handle(): void
    {
        $members = User::query()
            ->where('rostered', true)
            ->select('id')
            ->orderBy('id');
        $memberCount = (clone $members)->count();
        $queued = 0;
        $pageDelay = $this->pageDelay();

        Log::debug('Queueing VATSIM member statistics syncs', [
            'year' => $this->year,
            'month' => $this->month,
            'member_count' => $memberCount,
            'page_delay_seconds' => $pageDelay,
        ]);

        $members
            ->eachById(function (User $user) use (&$queued): void {
                SyncVatsimMemberSessions::dispatch($user->id, $this->year, $this->month)
                    ->delay(now()->addSeconds($queued * $this->pageDelay()));
                $queued++;
            });

        Log::debug('Queued VATSIM member statistics syncs', [
            'year' => $this->year,
            'month' => $this->month,
            'member_count' => $queued,
            'page_delay_seconds' => $pageDelay,
        ]);
    }

    private function pageDelay(): int
    {
        return (int) ceil(60 / max(1, config('app.vatsim_stats_sync_rate_limit')));
    }
}
