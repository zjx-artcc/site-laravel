<?php

namespace App\Jobs;

use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\StatisticsPrefixes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncVatsimMemberSessions implements ShouldQueue
{
    use Queueable;

    private const PAGE_SIZE = 100;

    public int $timeout = 300;

    public int $tries = 100;

    public array $backoff = [60, 300];

    public function __construct(
        public int $userId,
        public int $year,
        public int $month,
        public int $offset = 0,
        public ?int $total = null,
    ) {}

    public function middleware(): array
    {
        return [new RateLimited('vatsim-atc-sessions')];
    }

    public function handle(): void
    {
        $from = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $prefixes = StatisticsPrefixes::pluck('name')->toArray();
        Log::debug('Starting VATSIM member statistics sync', [
            'user_id' => $this->userId,
            'year' => $this->year,
            'month' => $this->month,
            'offset' => $this->offset,
            'total_count' => $this->total,
            'prefix_count' => count($prefixes),
        ]);

        // The endpoint rejects a page whose offset + limit is greater than
        // the total result count. Shrink the final request to fit exactly.
        $limit = $this->total === null
            ? self::PAGE_SIZE
            : min(self::PAGE_SIZE, $this->total - $this->offset);

        if ($limit <= 0) {
            $this->recomputeMonthlyStats($from, $to);

            return;
        }

        $response = $this->requestPage($limit, $this->offset);

        if (! $response->successful()) {
            Log::warning('VATSIM member statistics page request failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
                'user_id' => $this->userId,
                'year' => $this->year,
                'month' => $this->month,
                'limit' => $limit,
                'offset' => $this->offset,
            ]);

            $this->release($response->status() === 429 ? 65 : 60);

            return;
        }

        $payload = $response->json();
        $items = $payload['items'] ?? null;
        $count = $payload['count'] ?? null;

        if (! is_array($items) || ! is_int($count) || $count < 0) {
            Log::error('VATSIM member statistics sync returned an invalid page', [
                'user_id' => $this->userId,
                'year' => $this->year,
                'month' => $this->month,
                'limit' => $limit,
                'offset' => $this->offset,
            ]);

            return;
        }

        $syncedOnPage = 0;

        foreach ($items as $session) {
            if ($this->syncSession($session, $prefixes)) {
                $syncedOnPage++;
            }
        }

        $itemsCount = count($items);
        $nextOffset = $this->offset + $itemsCount;
        $reachedMonthBoundary = $this->containsSessionBefore($items, $from);

        Log::debug('Processed VATSIM member statistics page', [
            'user_id' => $this->userId,
            'year' => $this->year,
            'month' => $this->month,
            'limit' => $limit,
            'offset' => $this->offset,
            'items_count' => $itemsCount,
            'total_count' => $count,
            'synced_count' => $syncedOnPage,
            'reached_month_boundary' => $reachedMonthBoundary,
        ]);

        if ($itemsCount === 0 && $this->offset < $count) {
            Log::warning('VATSIM member statistics sync received an empty page before the result count', [
                'user_id' => $this->userId,
                'year' => $this->year,
                'month' => $this->month,
                'total' => $count,
                'offset' => $this->offset,
            ]);
        }

        // The member history is returned newest first. Once a page contains a
        // session before the requested month, all later pages are older too.
        if ($itemsCount > 0 && ! $reachedMonthBoundary && $nextOffset < $count) {
            self::dispatch($this->userId, $this->year, $this->month, $nextOffset, $count)
                ->delay(now()->addSeconds($this->pageDelay()));

            return;
        }

        $this->recomputeMonthlyStats($from, $to);

        Log::debug('Completed VATSIM member statistics sync', [
            'user_id' => $this->userId,
            'year' => $this->year,
            'month' => $this->month,
            'total_count' => $count,
            'synced_count' => $syncedOnPage,
        ]);
    }

    private function requestPage(int $limit, int $offset): Response
    {
        return Http::timeout(60)
            ->get(rtrim(config('app.vatsim_api_url') ?: 'https://api.vatsim.net', '/')."/v2/members/{$this->userId}/atc", [
                'limit' => $limit,
                'offset' => $offset,
            ]);
    }

    private function syncSession(mixed $session, array $prefixes): bool
    {
        if (! is_array($session)) {
            return false;
        }

        $connection = $session['connection_id'] ?? null;
        if (! is_array($connection)) {
            return false;
        }

        $sessionId = $connection['id'] ?? null;
        $vatsimId = $connection['vatsim_id'] ?? null;
        $callsign = $connection['callsign'] ?? null;
        $loggedOn = $connection['start'] ?? null;
        $loggedOff = $connection['end'] ?? null;

        if (! is_numeric($sessionId) || (int) $vatsimId !== $this->userId || ! is_string($callsign) || ! $loggedOn || ! $loggedOff) {
            return false;
        }

        try {
            $start = Carbon::parse($loggedOn);
            $end = Carbon::parse($loggedOff);
        } catch (\Throwable) {
            return false;
        }

        if (! Str::startsWith($callsign, $prefixes)) {
            return false;
        }

        $facilityLevel = $this->facilityLevel(Str::upper(Str::substr($callsign, -3)));
        if ($facilityLevel < 2) {
            return false;
        }

        ControllerSession::updateOrCreate(
            ['id' => (int) $sessionId],
            [
                'callsign' => $callsign,
                'user_id' => $this->userId,
                'facility_level' => $facilityLevel,
                'start' => $start,
                'end' => $end,
            ]
        );

        return true;
    }

    private function containsSessionBefore(array $sessions, Carbon $from): bool
    {
        foreach ($sessions as $session) {
            if (! is_array($session)) {
                continue;
            }

            $connection = $session['connection_id'] ?? null;
            if (! is_array($connection)) {
                continue;
            }

            $start = $connection['start'] ?? null;

            if (! $start) {
                continue;
            }

            try {
                if (Carbon::parse($start)->lessThan($from)) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
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

    private function pageDelay(): int
    {
        return (int) ceil(60 / max(1, config('app.vatsim_stats_sync_rate_limit')));
    }

    private function recomputeMonthlyStats(Carbon $from, Carbon $to): void
    {
        $sessions = ControllerSession::query()
            ->where('user_id', $this->userId)
            ->whereBetween('start', [$from, $to])
            ->get();

        if ($sessions->isEmpty()) {
            return;
        }

        $hours = [2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.0];

        foreach ($sessions as $session) {
            $duration = $session->end->diffInSeconds($session->start, true) / 3600;
            if (isset($hours[$session->facility_level])) {
                $hours[$session->facility_level] += $duration;
            }
        }

        ControllerMonthlyStat::updateOrCreate(
            ['user_id' => $this->userId, 'year' => $this->year, 'month' => $this->month],
            [
                'delivery_hours' => $hours[2],
                'ground_hours' => $hours[3],
                'tower_hours' => $hours[4],
                'approach_hours' => $hours[5],
                'center_hours' => $hours[6],
            ]
        );

        Log::debug('Recomputed VATSIM member monthly statistics', [
            'user_id' => $this->userId,
            'year' => $this->year,
            'month' => $this->month,
            'session_count' => $sessions->count(),
            'hours' => $hours,
        ]);
    }
}
