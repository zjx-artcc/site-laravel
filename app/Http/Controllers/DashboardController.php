<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $homeControllers = User::where('rostered', true)->where('facility', 'ZJX')->count();
        $visitingControllers = User::where('rostered', true)->whereNot('facility', 'ZJX')->count();

        return view('admin.index', [
            'homeControllers' => $homeControllers,
            'visitingControllers' => $visitingControllers,
            'statisticsSyncFromDate' => $now->copy()->subDays(StatisticsController::DEFAULT_LOOKBACK_DAYS)->toDateString(),
            'statisticsSyncToDate' => $now->toDateString(),
        ]);
    }
}
