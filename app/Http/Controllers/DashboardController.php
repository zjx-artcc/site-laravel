<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\News;
use App\Models\OnlineController;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $homeControllers = User::where('rostered', true)->where('facility', 'ZJX')->count();
        $visitingControllers = User::where('rostered', true)->whereNot('facility', 'ZJX')->count();
        $totalRosteredUsers = $homeControllers + $visitingControllers;
        $onlineControllers = OnlineController::count();
        $eventsThisMonth = Event::whereBetween('start', [now()->startOfMonth(), now()->endOfMonth(),])->count();
        $upcomingEvents = Event::whereBetween('start', [now(), now()->addDays(14)])->orderBy('start')->get();
        $trainingAssignments = TrainingAssignment::whereNotNull('instructor_id')->count();
        $trainingRequests = TrainingAssignment::whereNull('instructor_id')->count();
        $news = News::where('published_at', '<=', now())->orderBy('published_at', 'desc')->take(3)->get();


        return view('admin.index', [
            'homeControllers' => $homeControllers,
            'visitingControllers' => $visitingControllers,
            'totalRosteredUsers' => $totalRosteredUsers,
            'statisticsSyncFromDate' => $now->copy()->subDays(StatisticsController::DEFAULT_LOOKBACK_DAYS)->toDateString(),
            'statisticsSyncToDate' => $now->toDateString(),
            'onlineControllers' => $onlineControllers,
            'eventsThisMonth' => $eventsThisMonth,
            'upcomingEvents' => $upcomingEvents,
            'trainingAssignments' => $trainingAssignments,
            'trainingRequests' => $trainingRequests,
            'news' => $news,
        ]);
    }
}
