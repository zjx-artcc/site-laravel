<?php

namespace App\Http\Controllers;

use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Log;

class UserController extends Controller
{

    public function show(int $id) {
        $user = User::findOrFail($id);
        $now  = Carbon::now();

        $allStats = ControllerMonthlyStat::where('user_id', $id)->get();

        $totalHours = $allStats->sum(fn($s) => $s->totalHours());
        $monthHours = $allStats
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->sum(fn($s) => $s->totalHours());
        $yearHours  = $allStats
            ->where('year', $now->year)
            ->sum(fn($s) => $s->totalHours());

        $recentSessions = ControllerSession::where('user_id', $id)
            ->orderBy('start', 'desc')
            ->limit(10)
            ->get();

        return view('users.show', [
            'user'           => $user,
            'totalHours'     => $totalHours,
            'monthHours'     => $monthHours,
            'yearHours'      => $yearHours,
            'recentSessions' => $recentSessions,
        ]);
    }

    public function edit(Request $request, int $id) {
        $user = User::findOrFail($id);
        $authenticatedUser = Auth::user();

        if ($authenticatedUser->id != $user->id && !$authenticatedUser->hasPermissionTo('manage users')) {
            return response('Unauthorized', 403);
        }

        return view('users.edit', ['user'=> $user]);
    }

    public function update(Request $request, int $id) {
        $validated = $request->validate([
            'operatingInitials' => 'string|nullable|size:2', // can only be edited if admin
            'image' => 'file|image|mimes:jpeg,png,jpg,gif,svg|max:2048|nullable',
            'biography' => 'string|nullable|max:1000'
        ], [
            'operatingInitials.max' => 'Operating initials must be 2 characters long'
        ]);

        if (Auth::user()->id != $id && !Auth::user()->hasPermissionTo('manage users')) {
            return response('Unauthorized', 403 );
        }

        $user = User::findOrFail($id);

        if ($request->hasFile('image')) {
            $imageName = 'profile_'.$user->id.'.'.$request->file('image')->getClientOriginalExtension();
            $path = $request->file('image')->storeAs('profile', $imageName, 'public');

            $user->profile_image_route = 'storage/'.$path;
        }

        $user->biography = $validated['biography'] ?? null;

        if (Auth::user()->hasPermissionTo('manage users') && isset($validated['operatingInitials'])) {
            $oiCount = User::where('operating_initials', strtoupper($validated['operatingInitials']))->where('id', '!=', $id)->count();

            if ($oiCount > 0) {
                return redirect()->back()->with('error', 'OIs already assigned.');
            }

            $user->operating_initials = strtoupper($validated['operatingInitials'] ?? $user->operating_initials);
        }

        $user->save();
        return redirect()->route('users.edit', ['user' => $user->id])->with('success', 'User updated successfully');
    }

    public function trainingAssignments(int $id) {
        $user = User::findOrFail($id);

        if (Auth::id() !== $user->id && !Auth::user()->hasRole('training')) {
            abort(403);
        }

        if (!$user->rostered) {
            return redirect()->back()->with('error', 'Training assignments are only available for rostered users.');
        }

        $trainingAssignments = $user->trainingAssignmentsAsStudent()->paginate(25, ['*'], 'assignmentsPage');

        return view('users.training-assignments', [
            'user' => $user,
            'trainingAssignments' => $trainingAssignments
        ]);
    }

    public function trainingTickets(int $id) {
        $user = User::findOrFail($id);

        if (Auth::id() !== $user->id && !Auth::user()->hasRole('training')) {
            abort(403);
        }

        if (!$user->rostered) {
            return redirect()->back()->with('error', 'Training tickets are only available for rostered users.');
        }

        $trainingTickets = $user->trainingTicketsAsStudent()->paginate(25, ['*'], 'ticketsPage');

        return view('users.training-tickets', [
            'user' => $user,
            'trainingTickets' => $trainingTickets
        ]);
    }

    public function registeredEvents(int $id) {
        $user = User::findorFail($id);

        $registeredEvents = $user->events()
            ->withPivot('requested_position', 'start', 'end', 'position_status', 'assigned_position', 'assigned_start', 'assigned_end')
            ->paginate(25, ['*'], 'eventsPage');

        return view('users.registered-events', [
            'user' => $user,
            'userId' => $user->id,
            'registeredEvents' => $registeredEvents,
        ]);
    }

    public function soloCerts(int $id) {
        $user = User::findOrFail($id);

        if (Auth::id() !== $user->id && !Auth::user()->hasRole('training')) {
            abort(403);
        }

        if (!$user->rostered) {
            return redirect()->back()->with('error', 'Solo certifications are only available for rostered users.');
        }

        $soloCerts = $user->soloCerts()->paginate(25, ['*'], 'soloCertsPage');

        return view('users.solo-certs', [
            'user' => $user,
            'soloCerts' => $soloCerts
        ]);
    }
}
