<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class TrainingAssignmentTable extends Component
{
    /** Grouping key shared by every completed/forfeited row. */
    public const CLOSED_GROUP = 'closed';

    /**
     * The rows grouped into one section per training type.
     */
    public Collection $groups;

    /**
     * Whether the viewer may edit statuses and reach the Edit page.
     *
     * Resolved once here rather than per row: the answer is the same for the
     * whole page, and asking in the template meant one permission lookup per
     * row plus another inside each status cell.
     */
    public bool $canManageStudents;

    /**
     * Create a new component instance.
     */
    public function __construct(public mixed $trainingAssignments)
    {
        $this->canManageStudents = (bool) auth()->user()?->hasPermissionTo('manage students');

        // The query already orders closed-last, then by course seniority, then
        // by request age, so grouping here preserves that order and keeps the
        // Blade file a pure template. Grouping is per page: a type spanning a
        // page boundary gets its heading repeated on the next page, which is
        // what we want.
        //
        // Completed and forfeited rows share one key, so they collapse into a
        // single section instead of being split across the type groups.
        $this->groups = collect($trainingAssignments->items())
            ->groupBy(fn ($assignment) => $assignment->status->isClosed()
                ? self::CLOSED_GROUP
                : $assignment->training_type->value);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.training-assignment-table');
    }
}
