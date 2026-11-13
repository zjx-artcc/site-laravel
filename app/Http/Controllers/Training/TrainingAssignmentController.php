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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class TrainingAssignmentController extends Controller
{
    public function index(Request $request)
    {
        // `min:0` passed anything with a length, so ?trainingType=abc reached
        // the integer column and Postgres rejected it with a 500 (SQLite
        // coerces, so the test suite never saw it).
        $validated = $request->validate([
            'search' => 'nullable|string',
            'trainingType' => ['nullable', Rule::enum(TrainingType::class)],
        ]);

        $query = TrainingAssignment::search($request->input('search'));

        // blank() rather than is_null(): the "None" option submits an empty
        // string, and only the ConvertEmptyStringsToNull middleware makes that
        // arrive as null. Without this the filter would run
        // `where('training_type', '')` against an integer column.
        if (! blank($validated['trainingType'] ?? null)) {
            $query->where('training_type', $validated['trainingType']);
        }

        // Completed and forfeited assignments are out of training, so they are
        // hidden unless asked for. Inactive-but-open ones still show: the status
        // badge says where each stands, and hiding those left no way to reach
        // them from the UI at all.
        $showClosed = $request->boolean('showClosed');

        $closedStatuses = TrainingStatus::closedValues();

        if (! $showClosed) {
            $query->whereNotIn('status', $closedStatuses);
        }

        // Ordering, all of it applied to the Scout builder so it happens before
        // pagination rather than to one page of already-sliced results:
        //
        //   1. closed rows last, so they land in the single
        //      "Completed / Forfeited" section the table renders at the bottom;
        //   2. then course seniority, which the table groups on (the column
        //      holds the enum's backing value — just the order cases were added
        //      — so the ranking has to be mapped here);
        //   3. then oldest request first inside each group.
        $query->orderBy(DB::raw(sprintf(
            'CASE WHEN status IN (%s) THEN 1 ELSE 0 END',
            implode(', ', $closedStatuses)
        )), 'asc');

        $query->orderBy(DB::raw('CASE training_type
                WHEN 4 THEN 0   -- C1
                WHEN 7 THEN 1   -- F11 TRACON
                WHEN 3 THEN 2   -- S3
                WHEN 6 THEN 3   -- MCO TWR
                WHEN 2 THEN 4   -- S2
                WHEN 5 THEN 5   -- MCO GND
                WHEN 1 THEN 6   -- S1
            END'), 'asc')
            ->orderBy('created_at', 'asc');

        // withQueryString() so paging keeps the search term and type filter;
        // without it every page link is a bare ?page=N.
        $trainingAssignments = $query->paginate(20)->withQueryString();

        // A request can name a page that no longer exists: the inline status
        // change reloads the page it was on, and marking the last row of the
        // last page Complete takes that page away with it. Laravel renders an
        // empty paginator rather than redirecting, which reads as "there are no
        // training assignments" while earlier pages are still full — so bounce
        // to the last real page instead.
        if ($trainingAssignments->isEmpty() && $trainingAssignments->currentPage() > 1) {
            return redirect()->route('training-assignments.index', array_merge(
                $request->query(),
                ['page' => $trainingAssignments->lastPage()],
            ));
        }

        // Headline counters, deliberately independent of the search/type filters
        // so they read as a stable picture of the queue rather than shifting
        // with whatever is typed in the search box. Both ignore completed and
        // forfeited assignments: those are out of training either way. They also
        // require `active`, which excludes the rows parked under the Edit page's
        // old Active checkbox — those are still listed, marked Inactive, but
        // claim() and drop() refuse them, so counting them as requests waiting
        // to be picked up would overstate the queue.
        // Both numbers describe the same set of rows, split on whether an
        // instructor is assigned, so they come back from one aggregate rather
        // than two counts over the same scan. CASE rather than COUNT(*) FILTER
        // to keep it portable between Postgres and the SQLite test database.
        $counts = TrainingAssignment::query()
            ->where('active', true)
            ->whereNotIn('status', $closedStatuses)
            ->selectRaw('SUM(CASE WHEN instructor_id IS NULL THEN 1 ELSE 0 END) AS pending')
            ->selectRaw('SUM(CASE WHEN instructor_id IS NOT NULL THEN 1 ELSE 0 END) AS in_training')
            ->first();

        $pendingCount = (int) ($counts->pending ?? 0);
        $inTrainingCount = (int) ($counts->in_training ?? 0);

        return view('training-assignment.index', compact(
            'trainingAssignments',
            'pendingCount',
            'inTrainingCount',
        ));
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
            'status' => ['required', Rule::enum(TrainingStatus::class)],
            'trainingType' => ['required', Rule::enum(TrainingType::class)],
            'notifyUser' => 'sometimes|in:on,1',
        ]);

        $trainingAssignment = TrainingAssignment::findOrFail($id);
        $status = TrainingStatus::from((int) $validated['status']);

        // Saving derives `active` from the status, so saving a parked row wakes
        // it even when only the instructor or type was touched. That is fine on
        // its own, but not when the student has picked up another assignment
        // since: they would hold two live rows, which lets two trainers claim
        // them and sidesteps the one-active-assignment rule in create().
        if (! $trainingAssignment->active && ! $status->isClosed()) {
            $clash = TrainingAssignment::where('user_id', $trainingAssignment->user_id)
                ->where('active', true)
                ->whereKeyNot($trainingAssignment->getKey())
                ->exists();

            if ($clash) {
                return redirect()->back()->with('error', 'This student already has an active training assignment.');
            }
        }

        $trainingAssignment->instructor_id = $validated['instructorId'];
        $trainingAssignment->status = $validated['status'];
        $trainingAssignment->training_type = $validated['trainingType'];

        // `active` is derived from the status rather than edited on its own.
        // It used to be a checkbox beside the status dropdown, which let the two
        // disagree: an assignment marked Complete with Active still ticked kept
        // blocking the student's next request (create() refuses one while any
        // active assignment exists), and an open assignment with Active
        // unticked sat in the queue refusing claim() and drop(). Nothing in the
        // app used that second axis deliberately.
        $trainingAssignment->active = ! $status->isClosed();
        $trainingAssignment->save();

        if ($validated['notifyUser'] ?? false) {
            Mail::to($trainingAssignment->student->email)->bcc($trainingAssignment->instructor ?? null)->queue(new TrainingAssignmentUpdated($trainingAssignment));
        }

        Log::info('Training assignment updated', [
            'assignment_id' => $trainingAssignment->id,
            'updated_by' => $user->id,
        ]);

        // Back to the queue rather than back() to the form that was just saved:
        // the row's new status, section and badge are all only visible there.
        return redirect()->route('training-assignments.index')->with('success', 'Training request updated successfully');
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

        if ($assignment->user_id == $user->id || $user->hasPermissionTo('training-tickets:write')) {
            $assignment->active = false;
            $assignment->status = TrainingStatus::FORFEIT;
            $assignment->save();

            return redirect()->back()->with('success', 'Training assignment deactivated successfully');
        } else {
            return redirect()->back(400)->with('error', 'Invalid permissions');
        }
    }
}
