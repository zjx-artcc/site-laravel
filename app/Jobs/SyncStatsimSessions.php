<?php

namespace App\Jobs;

use App\Jobs\Concerns\LogsJobRun;
use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\StatisticsPrefixes;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncStatsimSessions implements ShouldQueue
{
    use LogsJobRun, Queueable;

    public int $timeout = 300;

    public function __construct(public int $year, public int $month) {}

    public function handle(): void
    {
        $from = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $this->startRun([
            'year' => $this->year,
            'month' => $this->month,
            'range' => $from->toDateString().' to '.$to->toDateString(),
        ]);

        $response = Http::timeout(60)
            ->withHeaders(['X-Api-Key' => config('app.statsim_api_key') ?? ''])
            ->get('https://api.statsim.net/api/atcsessions/dates', [
                'from' => $from->format('n/j/Y'),
                'to' => $to->format('n/j/Y'),
            ]);

        if (! $response->successful()) {
            $this->abortRun('Statsim API request failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
                'year' => $this->year,
                'month' => $this->month,
            ]);

            return;
        }

        $sessions = $response->json() ?? [];
        if (! is_array($sessions)) {
            $this->abortRun('Statsim returned a non-array payload', [
                'year' => $this->year,
                'month' => $this->month,
            ]);

            return;
        }

        $prefixes = StatisticsPrefixes::pluck('name')->toArray();
        $rosteredCids = User::where('rostered', true)->pluck('id')->flip();
        $touchedUserIds = [];

        $this->setMetric('sessions_received', count($sessions));
        $this->runDebug('fetched sessions', [
            'prefixes' => $prefixes,
            'rostered_controllers' => $rosteredCids->count(),
        ]);

        foreach ($sessions as $session) {
            $callsign = $session['callsign'] ?? null;
            $vatsimId = $session['vatsimid'] ?? null;
            $sessionId = $session['id'] ?? null;
            $loggedOn = $session['loggedOn'] ?? null;
            $loggedOff = $session['loggedOff'] ?? null;

            if (! $callsign || ! $vatsimId || ! $sessionId || ! $loggedOn || ! $loggedOff) {
                $this->countMetric('skipped_incomplete');

                continue;
            }

            if (! Str::startsWith($callsign, $prefixes)) {
                $this->countMetric('skipped_foreign_prefix');

                continue;
            }

            $userId = (int) $vatsimId;
            if (! $rosteredCids->has($userId)) {
                $this->countMetric('skipped_unrostered');

                continue;
            }

            $facilityLevel = $this->facilityLevel(Str::upper(Str::substr($callsign, -3)));
            if ($facilityLevel < 2) {
                $this->countMetric('skipped_unrated_position');

                continue;
            }

            ControllerSession::updateOrCreate(
                ['id' => (int) $sessionId],
                [
                    'callsign' => $callsign,
                    'user_id' => $userId,
                    'facility_level' => $facilityLevel,
                    'start' => $loggedOn,
                    'end' => $loggedOff,
                ]
            );

            $this->countMetric('sessions_stored');
            $touchedUserIds[$userId] = true;
        }

        foreach (array_keys($touchedUserIds) as $userId) {
            $this->recomputeMonthlyStats($userId, $this->year, $this->month);
            $this->countMetric('controllers_recomputed');
        }

        $this->finishRun(['year' => $this->year, 'month' => $this->month]);
    }

    private function facilityLevel(string $suffix): int
    {
        return match ($suffix) {
            'DEL' => 2,
            'GND' => 3,
            'TWR' => 4,
            'APP', 'DEP' => 5,
            'CTR', 'FSS' => 6,
            default => 0,
        };
    }

    private function recomputeMonthlyStats(int $userId, int $year, int $month): void
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $sessions = ControllerSession::where('user_id', $userId)
            ->whereBetween('start', [$from, $to])
            ->get();

        $hours = [2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.0];

        foreach ($sessions as $session) {
            $duration = $session->end->diffInSeconds($session->start, true) / 3600;
            if (isset($hours[$session->facility_level])) {
                $hours[$session->facility_level] += $duration;
            }
        }

        ControllerMonthlyStat::updateOrCreate(
            ['user_id' => $userId, 'year' => $year, 'month' => $month],
            [
                'delivery_hours' => $hours[2],
                'ground_hours' => $hours[3],
                'tower_hours' => $hours[4],
                'approach_hours' => $hours[5],
                'center_hours' => $hours[6],
            ]
        );
    }
}
