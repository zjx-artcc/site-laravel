<?php

namespace App\Http\Controllers;

use App\Jobs\SyncStatsimSessions;
use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StatisticsController extends Controller
{
    public function sync(Request $request)
    {
        $request->validate([
            'from_year'  => 'required|integer|min:2000|max:2100',
            'from_month' => 'required|integer|min:1|max:12',
            'to_year'    => 'required|integer|min:2000|max:2100',
            'to_month'   => 'required|integer|min:1|max:12',
        ]);

        $from = Carbon::create($request->from_year, $request->from_month, 1)->startOfMonth();
        $to   = Carbon::create($request->to_year, $request->to_month, 1)->startOfMonth();

        if ($from->greaterThan($to)) {
            return back()->withErrors(['from' => 'Start date must be before or equal to end date.']);
        }

        $count = 0;
        $cursor = $from->copy();
        while ($cursor->lessThanOrEqualTo($to)) {
            SyncStatsimSessions::dispatch($cursor->year, $cursor->month);
            $cursor->addMonthNoOverflow();
            $count++;
        }

        return back()->with('success', "Queued sync for {$count} month(s): {$from->format('M Y')} – {$to->format('M Y')}.");
    }

    public function index(Request $request)
    {
        $now   = Carbon::now();
        $yearParam = $request->query('year', $now->year);
        $year  = ($yearParam === 'all' || (int) $yearParam === 0) ? 0 : (int) $yearParam;
        $month = $request->query('month', $now->month);
        $cid   = $request->query('cid');

        if ($year !== 0 && ($year < 2000 || $year > 2100)) $year = $now->year;
        $month = ($month === 'all' || (int) $month === 0) ? 0 : (int) $month;
        if ($month !== 0 && ($month < 1 || $month > 12)) $month = $now->month;

        $years = ControllerMonthlyStat::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if (!$years->contains($now->year)) {
            $years = $years->prepend($now->year);
        }

        $controllers = User::where('rostered', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'rating']);

        // Individual controller lookup
        $selectedController = null;
        $controllerMonthly  = collect();
        $controllerSessions = collect();

        if ($cid) {
            $selectedController = User::find($cid);

            if ($selectedController) {
                $monthlyQuery = ControllerMonthlyStat::where('user_id', $cid);
                if ($year !== 0) $monthlyQuery->where('year', $year);
                $controllerMonthly = $monthlyQuery
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();

                if ($month !== 0 && $year !== 0) {
                    $from = Carbon::create($year, $month, 1)->startOfMonth();
                    $to   = $from->copy()->endOfMonth();
                    $controllerSessions = ControllerSession::where('user_id', $cid)
                        ->whereBetween('start', [$from, $to])
                        ->orderBy('start', 'desc')
                        ->get();
                }
            }
        }

        // Leaderboard (only shown when no controller selected)
        $leaderboardQuery = ControllerMonthlyStat::with('user');
        if ($year !== 0) $leaderboardQuery->where('year', $year);
        if ($month !== 0) $leaderboardQuery->where('month', $month);

        if ($month === 0) {
            $rows = $leaderboardQuery
                ->get()
                ->filter(fn($s) => $s->user !== null)
                ->groupBy('user_id')
                ->map(function ($group) {
                    $first = $group->first();
                    $first->delivery_hours = $group->sum('delivery_hours');
                    $first->ground_hours   = $group->sum('ground_hours');
                    $first->tower_hours    = $group->sum('tower_hours');
                    $first->approach_hours = $group->sum('approach_hours');
                    $first->center_hours   = $group->sum('center_hours');
                    return $first;
                })
                ->sortByDesc(fn($s) => $s->totalHours())
                ->values();
        } else {
            $rows = $leaderboardQuery
                ->get()
                ->filter(fn($s) => $s->user !== null)
                ->sortByDesc(fn($s) => $s->totalHours())
                ->values();
        }

        $totals = [
            'delivery' => $rows->sum('delivery_hours'),
            'ground'   => $rows->sum('ground_hours'),
            'tower'    => $rows->sum('tower_hours'),
            'approach' => $rows->sum('approach_hours'),
            'center'   => $rows->sum('center_hours'),
            'total'    => $rows->sum(fn($s) => $s->totalHours()),
        ];

        $allTimeHours = ControllerMonthlyStat::selectRaw(
            'SUM(delivery_hours + ground_hours + tower_hours + approach_hours + center_hours) as total'
        )->value('total') ?? 0;

        $earliest = ControllerMonthlyStat::selectRaw('MIN(year * 100 + month) as ym, MIN(year) as y, MIN(month) as m')->first();
        $allTimeSince = ($earliest && $earliest->y)
            ? Carbon::create($earliest->y, $earliest->m, 1)->format('M Y')
            : null;

        return view('statistics.index', [
            'stats'               => $rows,
            'year'                => $year,
            'month'               => $month,
            'years'               => $years,
            'totals'              => $totals,
            'allTimeHours'        => $allTimeHours,
            'allTimeSince'        => $allTimeSince,
            'controllers'         => $controllers,
            'cid'                 => $cid,
            'selectedController'  => $selectedController,
            'controllerMonthly'   => $controllerMonthly,
            'controllerSessions'  => $controllerSessions,
        ]);
    }
}
