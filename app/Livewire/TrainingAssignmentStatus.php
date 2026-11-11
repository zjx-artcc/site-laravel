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

        if ($new !== $this->assignment->status) {
            $this->assignment->status = $new;
            $this->assignment->save();
        }
    }

    public function render()
    {
        return view('livewire.training-assignment-status');
    }
}
