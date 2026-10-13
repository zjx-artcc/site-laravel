<?php

namespace App\Http\Controllers;

use App\Jobs\SyncVatsimSessions;
use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        if ($month === 0) {
            // Aggregate per-controller totals in SQL rather than loading every
            // monthly row into memory and summing in PHP (unbounded over time).
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
        } else {
            $rows = $leaderboardQuery
                ->get()
                ->filter(fn ($s) => $s->user !== null)
                ->sortByDesc(fn ($s) => $s->totalHours())
                ->values();
        }

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
}
