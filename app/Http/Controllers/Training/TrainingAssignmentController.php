<?php

namespace App\Http\Controllers\Training;

use App\Enums\TrainingStatus;
use App\Enums\TrainingType;
use App\Http\Controllers\Controller;
use App\Jobs\SendTrainingRequestToWebhook;
use App\Mail\TrainingAssignmentCreated;
use App\Mail\TrainingAssignmentUpdated;
use App\Models\Staff;
use App\Models\TrainingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TrainingAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'trainingType' => 'sometimes|min:0',
            'showInactive' => 'nullable|sometimes:on',
        ]);

        $query = null;

        if (is_null($request->input('trainingType'))) {
            $query = TrainingAssignment::search($request->input('search'));
        } else {
            $query = TrainingAssignment::search($request->input('search'))->where('training_type', $validated['trainingType']);
        }

        if (! $request->boolean('showInactive')) {
            $query->where('active', true);
        }

        $trainingAssignments = $query->paginate(20);

        return view('training-assignment.index', compact('trainingAssignments'));
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'trainingType' => 'int|required',
        ], [
            'trainingType.required' => 'Training Type must be specified.',
        ]);

        if (is_null(TrainingType::tryFrom($request->input('trainingType')))) {
            return redirect()->back()->with('error', 'Invalid Training Type');
        }

        $activeAssignments = TrainingAssignment::where([
            'user_id' => Auth::user()->id,
            'active' => true,
        ])->get();

        if (count($activeAssignments) > 0) {
            return redirect()->back()->withErrors('Training already assigned');
        }

        if (! Auth::user()->rostered) {
            return redirect()->back()->withErrors('Not an active controller.');
        }

        $trainingAssignment = TrainingAssignment::create([
            'training_type' => $validated['trainingType'],
            'user_id' => Auth::user()->id,
            'instructor_id' => null,
        ]);

        SendTrainingRequestToWebhook::dispatch($trainingAssignment);
        Mail::to(Auth::user()->email)->queue(new TrainingAssignmentCreated($trainingAssignment));

        return redirect()->back()->with('success', 'Training requested successfully');
    }

    public function edit(int $id)
    {
        $trainingAssignment = TrainingAssignment::findOrFail($id);
        $instructors = Staff::orWhere([
            'title_short' => 'INS',
        ])->orWhere([
            'title_short' => 'MTR',
        ])->get();

        return view('training-assignment.edit', ['assignment' => $trainingAssignment, 'instructors' => $instructors]);
    }

    public function update(Request $request, int $id)
    {
        $user = Auth::user();

        if (! $user->hasPermissionTo('manage students')) {
            return redirect()->back()->with('error', 'Insufficient permissions to edit training assignment');
        }

        $validated = $request->validate([
            'instructorId' => 'string|nullable',
            'active' => 'sometimes|in:on,1',
            'status' => 'int|required|min:1',
            'trainingType' => 'int|required',
            'notifyUser' => 'sometimes|in:on,1',
        ]);

        $trainingAssignment = TrainingAssignment::findOrFail($id);
        $trainingAssignment->instructor_id = $validated['instructorId'];
        $trainingAssignment->active = $request->boolean('active');
        $trainingAssignment->status = $validated['status'];
        $trainingAssignment->training_type = $validated['trainingType'];
        $trainingAssignment->save();

        if ($validated['notifyUser'] ?? false) {
            Mail::to($trainingAssignment->student->email)->bcc($trainingAssignment->instructor ?? null)->queue(new TrainingAssignmentUpdated($trainingAssignment));
        }

        Log::info('Training assignment updated', [
            'assignment_id' => $trainingAssignment->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->back()->with('success', 'Training request updated successfully');
    }

    public function claim(Request $request, int $id)
    {
        if (! Auth::user()->hasPermissionTo('claim students')) {
            return redirect()->back()->with('error', 'You do not have permission to claim training assignments.');
        }

        $assignment = TrainingAssignment::findOrFail($id);

        if ($assignment->user_id == Auth::user()->id) {
            return redirect()->back()->with('error', 'You cannot claim yourself.');
        }

        if (! $assignment->active) {
            return redirect()->back()->with('error', 'Cannot update inactive training assignment.');
        }

        $assignment->update([
            'instructor_id' => Auth::user()->id,
        ]);

        Mail::to($assignment->student->email)->bcc($assignment->instructor ?? null)->queue(new TrainingAssignmentUpdated($assignment));

        return redirect()->back()->with('success', 'Training assignment claimed successfully');
    }

    public function drop(Request $request, int $id)
    {
        $user = Auth::user();
        if (! $user->hasPermissionTo('claim students')) {
            return redirect()->back()->with('error', 'You do not have permission to claim training assignments.');
        }

        $assignment = TrainingAssignment::findOrFail($id);

        if (! $assignment->active) {
            return redirect()->back()->with('error', 'Cannot update inactive training assignment.');
        }

        if ($assignment->instructor_id == $user->id || $user->hasPermissionTo('manage students')) {
            $assignment->update([
                'instructor_id' => null,
            ]);
        }

        Mail::to($assignment->student->email)->queue(new TrainingAssignmentUpdated($assignment));

        return redirect()->back()->with('success', 'Training assignment dropped successfully');
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'id' => 'string|required',
        ]);

        $assignment = TrainingAssignment::find($validated['id']);

        if (is_null($assignment)) {
            return redirect()->back(400)->with('error', 'Training assignment not found');
        }

        if ($assignment->user_id == $user->id || $user->hasPermissionTo('manage students')) {
            $assignment->active = false;
            $assignment->status = TrainingStatus::FORFEIT;
            $assignment->save();

            return redirect()->back()->with('success', 'Training assignment deactivated successfully');
        } else {
            return redirect()->back(400)->with('error', 'Invalid permissions');
        }
    }
}
