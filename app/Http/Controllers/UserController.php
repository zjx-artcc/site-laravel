<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Enums\LoaStatus;
use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function show(int $id)
    {
        $user = User::with('certifications.certificationLevel.facility')->findOrFail($id);

        $now = Carbon::now();

        $allStats = ControllerMonthlyStat::where('user_id', $id)->get();

        $totalHours = $allStats->sum(fn ($s) => $s->totalHours());
        $monthHours = $allStats
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->sum(fn ($s) => $s->totalHours());
        $yearHours = $allStats
            ->where('year', $now->year)
            ->sum(fn ($s) => $s->totalHours());

        $recentSessions = ControllerSession::where('user_id', $id)
            ->orderBy('start', 'desc')
            ->limit(10)
            ->get();

        return view('users.show', [
            'user' => $user,
            'totalHours' => $totalHours,
            'monthHours' => $monthHours,
            'yearHours' => $yearHours,
            'recentSessions' => $recentSessions,
        ]);
    }

    public function edit(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $authenticatedUser = Auth::user();

        if ($authenticatedUser->id != $user->id && ! $authenticatedUser->hasPermissionTo('manage users')) {
            return response('Unauthorized', 403);
        }

        return view('users.edit', ['user' => $user]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'operatingInitials' => 'string|nullable|size:2', // can only be edited if admin
            'image' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048|nullable',
            'biography' => 'string|nullable|max:1000',
        ], [
            'operatingInitials.max' => 'Operating initials must be 2 characters long',
            'image.image' => 'The profile picture must be an image file.',
            'image.mimes' => 'The profile picture must be a JPEG, PNG, or GIF file.',
            'image.max' => 'The profile picture must be smaller than 2MB.',
        ]);

        if (Auth::user()->id != $id && ! Auth::user()->hasPermissionTo('manage users')) {
            return response('Unauthorized', 403);
        }

        $user = User::findOrFail($id);

        if ($request->hasFile('image')) {
            $oldImagePath = $user->profileImageStoragePath();
            $image = $validated['image'];
            $imageName = 'profile_'.$user->id.'.'.$image->extension();
            $path = $image->storeAs('profile', $imageName, 'public');

            if ($oldImagePath && $oldImagePath !== $path) {
                Storage::disk('public')->delete($oldImagePath);
            }

            $user->profile_image_route = $path;
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

    public function trainingAssignments(int $id)
    {
        $user = User::findOrFail($id);

        $this->authorizeSensitivePage($user, 'training-assignments:read');

        if (! $user->rostered) {
            return redirect()->back()->with('error', 'Training assignments are only available for rostered users.');
        }

        $trainingAssignments = $user->trainingAssignmentsAsStudent()->paginate(25, ['*'], 'assignmentsPage');

        return view('users.training-assignments', [
            'user' => $user,
            'trainingAssignments' => $trainingAssignments,
        ]);
    }

    public function feedback(int $id)
    {
        $user = User::findOrFail($id);

        if (Auth::id() !== $user->id && ! Auth::user()->hasPermissionTo('feedback:read')) {
            abort(403);
        }

        $releasedFeedback = Feedback::where('controller_id', $id)
            ->where('status', FeedbackStatus::RELEASED)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('users.feedback', [
            'user' => $user,
            'releasedFeedback' => $releasedFeedback,
        ]);
    }

    public function trainingTickets(int $id)
    {
        $user = User::findOrFail($id);

        $this->authorizeSensitivePage($user, 'training-tickets:read');

        if (! $user->rostered) {
            return redirect()->back()->with('error', 'Training tickets are only available for rostered users.');
        }

        $trainingTickets = $user->trainingTicketsAsStudent()->paginate(25, ['*'], 'ticketsPage');

        return view('users.training-tickets', [
            'user' => $user,
            'trainingTickets' => $trainingTickets,
        ]);
    }

    public function loa(int $id)
    {
        $user = User::findOrFail($id);

        if (Auth::user()->id != $user->id) {
            return response('Unauthorized', 403);
        }

        $activeLoa = $user->loas()->where('status', '!=', LoaStatus::INACTIVE)->first();
        $loaHistory = $user->loas()->where('status', LoaStatus::INACTIVE)->paginate(25, ['*'], 'loaPage');

        return view('users.loa', [
            'user' => $user,
            'activeLoa' => $activeLoa,
            'loaHistory' => $loaHistory,
        ]);
    }

    public function registeredEvents(int $id)
    {
        $user = User::findOrFail($id);

        if (Auth::id() !== $user->id && ! Auth::user()->hasPermissionTo('assign event positions')) {
            abort(403);
        }

        $registeredEvents = $user->events()
            ->withPivot('requested_position', 'start', 'end', 'position_status', 'assigned_position', 'assigned_start', 'assigned_end')
            ->paginate(25, ['*'], 'eventsPage');

        return view('users.registered-events', [
            'user' => $user,
            'userId' => $user->id,
            'registeredEvents' => $registeredEvents,
        ]);
    }

    public function soloCerts(int $id)
    {
        $user = User::findOrFail($id);

        $this->authorizeSensitivePage($user, 'solo-certs:read');

        if (! $user->rostered) {
            return redirect()->back()->with('error', 'Solo certifications are only available for rostered users.');
        }

        $soloCerts = $user->soloCerts()->paginate(25, ['*'], 'soloCertsPage');

        return view('users.solo-certs', [
            'user' => $user,
            'soloCerts' => $soloCerts,
        ]);
    }

    private function authorizeSensitivePage(User $user, string $permission): void
    {
        if (Auth::id() !== $user->id && ! Auth::user()->can($permission)) {
            abort(403);
        }
    }
}
