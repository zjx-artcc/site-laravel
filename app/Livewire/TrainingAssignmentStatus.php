<?php

namespace App\Livewire;

use App\Enums\TrainingStatus;
use App\Models\TrainingAssignment;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The Status cell of the staff training assignments table, editable in place.
 *
 * Deliberately does not email the student: the Edit page keeps its explicit
 * "Notify user of changes" checkbox for that, and a cell you change with one
 * click should not be able to send mail by accident. The change is still
 * recorded in the activity log via the model's LogsActivity options.
 */
class TrainingAssignmentStatus extends Component
{
    public TrainingAssignment $assignment;

    public ?int $status = null;

    public function mount(TrainingAssignment $assignment): void
    {
        $this->authorizeWrite();

        $this->assignment = $assignment;
    }

    // Livewire action requests do not re-run the mounting route's permission
    // middleware, so every mutating action must re-check authorization itself.
    protected function authorizeWrite(): void
    {
        abort_unless(auth()->user()?->hasPermissionTo('manage students'), 403);
    }

    public function setStatus(int $status): void
    {
        $this->authorizeWrite();

        // Held on the component (rather than validated as a bare argument) so a
        // rejected value surfaces through the normal error bag in the cell.
        $this->status = $status;

        $this->validate([
            'status' => ['required', Rule::enum(TrainingStatus::class)],
        ]);

        $new = TrainingStatus::from($status);
        $was = $this->assignment->status;

        if ($new === $was) {
            return;
        }

        $this->assignment->status = $new;

        // `active` is the flag the rest of the app actually gates on, not the
        // status: create() refuses a new request while the student has an
        // active assignment, and claim()/drop() reject an inactive one. Left
        // behind, marking training Complete inline would still block the
        // student's next request, and reopening a closed row would put it back
        // in the queue with no way to claim it. Set unconditionally, which also
        // heals any row predating the removal of the Edit page's Active
        // checkbox, where the two were free to disagree.
        $this->assignment->active = ! $new->isClosed();

        $this->assignment->save();

        // A status change can only move a row across the open/closed line: the
        // table groups by training type (untouched here) and orders by date, so
        // the four open statuses just need the badge they already re-rendered.
        // Crossing the line does change the row's section, the group counts and
        // the total — all server-rendered outside this component — so the page
        // has to come back from the server.
        if ($new->isClosed() !== $was->isClosed()) {
            $this->dispatch('training-assignment-moved');
        }
    }

    public function render()
    {
        return view('livewire.training-assignment-status');
    }
}
