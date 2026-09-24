<?php

namespace App\Http\Controllers;

use App\Jobs\RemoveUserFromRoster;
use App\Jobs\SyncVatsimSessions;
use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\TrainingTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StatisticsController extends Controller
{
    public const DEFAULT_LOOKBACK_DAYS = 14;

    public function sync(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date|before_or_equal:to_date',
            'to_date' => 'required|date',
        ]);

        $from = Carbon::parse($request->from_date)->utc()->startOfDay();
        $to = Carbon::parse($request->to_date)->utc()->endOfDay();

        SyncVatsimSessions::dispatch($from->toIso8601String(), $to->toIso8601String());

        return back()->with('success', "Queued VATSIM Core statistics sync: {$from->toFormattedDateString()} – {$to->toFormattedDateString()}.");
    }

    public function index(Request $request)
    {
        $now = Carbon::now();
        $yearParam = $request->query('year', $now->year);
        $year = ($yearParam === 'all' || (int) $yearParam === 0) ? 0 : (int) $yearParam;
        $month = $request->query('month', $now->month);
        $cid = $request->query('cid');

        if ($year !== 0 && ($year < 2000 || $year > 2100)) {
            $year = $now->year;
        }
        $month = ($month === 'all' || (int) $month === 0) ? 0 : (int) $month;
        if ($month !== 0 && ($month < 1 || $month > 12)) {
            $month = $now->month;
        }

        $years = ControllerMonthlyStat::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if (! $years->contains($now->year)) {
            $years = $years->prepend($now->year);
        }

        $controllers = User::where('rostered', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'rating']);

        // Individual controller lookup
        $selectedController = null;
        $controllerMonthly = collect();
        $controllerSessions = collect();

        if ($cid) {
            $selectedController = User::find($cid);

            if ($selectedController) {
                $monthlyQuery = ControllerMonthlyStat::where('user_id', $cid);
                if ($year !== 0) {
                    $monthlyQuery->where('year', $year);
                }
                $controllerMonthly = $monthlyQuery
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();

                if ($month !== 0 && $year !== 0) {
                    $from = Carbon::create($year, $month, 1)->startOfMonth();
                    $to = $from->copy()->endOfMonth();
                    $controllerSessions = ControllerSession::where('user_id', $cid)
                        ->whereBetween('start', [$from, $to])
                        ->orderBy('start', 'desc')
                        ->get();
                }
            }
        }

        // Leaderboard (only shown when no controller selected)
        $leaderboardQuery = ControllerMonthlyStat::with('user');
        if ($year !== 0) {
            $leaderboardQuery->where('year', $year);
        }
        if ($month !== 0) {
            $leaderboardQuery->where('month', $month);
        }

        // Aggregate per-controller totals in SQL rather than loading every
        // monthly row into memory and summing in PHP (unbounded over time).
        // Grouping by user_id also collapses the multiple rows a single
        // controller would otherwise produce when a specific month is combined
        // with "All Years" (one row per year).
        $rows = $leaderboardQuery
            ->selectRaw('user_id,
                SUM(delivery_hours) as delivery_hours,
                SUM(ground_hours) as ground_hours,
                SUM(tower_hours) as tower_hours,
                SUM(approach_hours) as approach_hours,
                SUM(center_hours) as center_hours')
            ->groupBy('user_id')
            ->get()
            ->filter(fn ($s) => $s->user !== null)
            ->sortByDesc(fn ($s) => $s->totalHours())
            ->values();

        $totals = [
            'delivery' => $rows->sum('delivery_hours'),
            'ground' => $rows->sum('ground_hours'),
            'tower' => $rows->sum('tower_hours'),
            'approach' => $rows->sum('approach_hours'),
            'center' => $rows->sum('center_hours'),
            'total' => $rows->sum(fn ($s) => $s->totalHours()),
        ];

        $allTimeHours = ControllerMonthlyStat::selectRaw(
            'SUM(delivery_hours + ground_hours + tower_hours + approach_hours + center_hours) as total'
        )->value('total') ?? 0;

        // Take the single earliest (year, month) pair so the month can't be
        // sourced from a different row than the year.
        $earliestYearMonth = (int) ControllerMonthlyStat::selectRaw('MIN(year * 100 + month) as ym')->value('ym');
        $allTimeSince = $earliestYearMonth > 0
            ? Carbon::create(intdiv($earliestYearMonth, 100), $earliestYearMonth % 100, 1)->format('M Y')
            : null;

        return view('statistics.index', [
            'stats' => $rows,
            'year' => $year,
            'month' => $month,
            'years' => $years,
            'totals' => $totals,
            'allTimeHours' => $allTimeHours,
            'allTimeSince' => $allTimeSince,
            'controllers' => $controllers,
            'cid' => $cid,
            'selectedController' => $selectedController,
            'controllerMonthly' => $controllerMonthly,
            'controllerSessions' => $controllerSessions,
        ]);
    }

    public function quarterly(Request $request)
    {
        $data = $this->resolveQuarterlyData($request);

        $perPage = 25;
        $data['flagged'] = $this->paginate($data['flagged'], $request, 'flagged_page', $perPage);
        $data['rows'] = $this->paginate($data['rows'], $request, 'rows_page', $perPage);

        return view('statistics.quarterly', $data);
    }

    private function paginate($items, Request $request, string $pageName, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query($pageName, 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageName, 'query' => $request->query()]
        );
    }

    public function exportQuarterly(Request $request)
    {
        $data = $this->resolveQuarterlyData($request);

        $rows = $data['flagged'];

        $filename = "quarterly-flagged-q{$data['quarter']}-{$data['year']}-".Carbon::now()->utc()->format('Ymd-His').'Z.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Controller CID', 'FULL Name', 'Status', 'Hours (In Quarter)', 'Training Hrs (Student)', 'Training Hrs (Instructor)'], ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->user->id,
                    $row->user->name,
                    $this->statusLabel($row->user),
                    number_format($row->total, 2),
                    number_format($row->training_student, 2),
                    number_format($row->training_instructor, 2),
                ], ',', '"', '\\');
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Total training hours per controller for the quarter, split into time spent
     * as a student and time spent as an instructor. Keyed by user id.
     */
    private function quarterlyTrainingHours($userIds, int $year, array $months): Collection
    {
        $start = Carbon::create($year, $months[0], 1)->startOfMonth();
        $end = Carbon::create($year, end($months), 1)->endOfMonth();

        $tickets = TrainingTicket::whereBetween('session_start', [$start, $end])
            ->where(fn ($q) => $q->whereIn('user_id', $userIds)->orWhereIn('instructor_id', $userIds))
            ->get(['user_id', 'instructor_id', 'session_start', 'session_end']);

        $hours = collect();

        foreach ($tickets as $ticket) {
            if (! $ticket->session_start || ! $ticket->session_end) {
                continue;
            }

            $seconds = Carbon::parse($ticket->session_end)->diffInSeconds(Carbon::parse($ticket->session_start), true);
            $duration = max(0, $seconds) / 3600;

            $student = $hours->get($ticket->user_id, ['student' => 0.0, 'instructor' => 0.0]);
            $student['student'] += $duration;
            $hours->put($ticket->user_id, $student);

            $instructor = $hours->get($ticket->instructor_id, ['student' => 0.0, 'instructor' => 0.0]);
            $instructor['instructor'] += $duration;
            $hours->put($ticket->instructor_id, $instructor);
        }

        return $hours;
    }

    public function removeInactive(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'reason' => 'required|string|max:255',
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();

        foreach ($users as $user) {
            RemoveUserFromRoster::dispatch($user->id, $validated['reason'], Auth::id());
            Log::info('Queued roster removal for user '.$user->id.' ('.$user->name.'). Reason: '.$validated['reason'].' by '.Auth::id());
        }

        return redirect()->back()->with('success', 'Queued removal for '.$users->count().' controller(s).');
    }

    private function statusLabel(User $user): string
    {
        if (! $user->rostered) {
            return 'Not Rostered';
        }

        if (strcasecmp($user->facility, config('app.vatusa_facility')) === 0) {
            return 'Home';
        }

        return "Visitor ({$user->facility})";
    }

    private function resolveQuarterlyData(Request $request): array
    {
        $now = Carbon::now();
        $currentQuarter = intdiv($now->month - 1, 3) + 1;

        $year = (int) $request->query('year', $now->year);
        if ($year < 2000 || $year > 2100) {
            $year = $now->year;
        }

        $quarter = (int) $request->query('quarter', $currentQuarter);
        if ($quarter < 1 || $quarter > 4) {
            $quarter = $currentQuarter;
        }

        $threshold = (float) $request->query('threshold', 3);
        if ($threshold < 0) {
            $threshold = 3;
        }

        $rosteredOnly = $request->boolean('rostered_only');
        $cid = $request->query('cid');

        $months = range(($quarter - 1) * 3 + 1, $quarter * 3);

        $years = ControllerMonthlyStat::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if (! $years->contains($now->year)) {
            $years = $years->prepend($now->year);
        }

        $activeUserIds = ControllerMonthlyStat::select('user_id')->distinct()->pluck('user_id');

        $quarterStats = ControllerMonthlyStat::whereIn('user_id', $activeUserIds)
            ->where('year', $year)
            ->whereIn('month', $months)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                'delivery' => $rows->sum('delivery_hours'),
                'ground' => $rows->sum('ground_hours'),
                'tower' => $rows->sum('tower_hours'),
                'approach' => $rows->sum('approach_hours'),
                'center' => $rows->sum('center_hours'),
                'total' => $rows->sum(fn ($r) => $r->totalHours()),
            ]);

        $users = User::whereIn('id', $activeUserIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'rating', 'rostered', 'facility']);

        $training = $this->quarterlyTrainingHours($activeUserIds, $year, $months);

        $empty = ['delivery' => 0, 'ground' => 0, 'tower' => 0, 'approach' => 0, 'center' => 0, 'total' => 0];
        $emptyTraining = ['student' => 0.0, 'instructor' => 0.0];

        $controllers = $users->map(fn ($user) => (object) [
            'id' => $user->id,
            'name' => $user->name,
            'rating' => $user->rating,
        ])->values();

        $rows = $users->map(function ($user) use ($quarterStats, $training, $empty, $emptyTraining) {
            $trainingHours = $training->get($user->id, $emptyTraining);

            return (object) array_merge(
                ['user' => $user],
                $quarterStats->get($user->id, $empty),
                [
                    'training_student' => $trainingHours['student'],
                    'training_instructor' => $trainingHours['instructor'],
                    'training_total' => $trainingHours['student'] + $trainingHours['instructor'],
                ]
            );
        })->values();

        if ($cid) {
            $rows = $rows->filter(fn ($row) => (string) $row->user->id === (string) $cid)->values();
        }

        // Only rostered controllers can be removed from the roster, so the flagged
        // list (the table the removal action operates on) always excludes anyone
        // who isn't currently rostered.
        $flagged = $rows->filter(fn ($row) => $row->total < $threshold && $row->user->rostered)
            ->sortBy('total')
            ->values();

        // The "Rostered only" toggle applies to the full breakdown table, which by
        // default includes everyone who has ever logged StatsSim hours.
        if ($rosteredOnly) {
            $rows = $rows->filter(fn ($row) => $row->user->rostered)->values();
        }

        return [
            'rows' => $rows,
            'flagged' => $flagged,
            'year' => $year,
            'quarter' => $quarter,
            'years' => $years,
            'threshold' => $threshold,
            'controllers' => $controllers,
            'cid' => $cid,
            'rosteredOnly' => $rosteredOnly,
        ];
    }
}
