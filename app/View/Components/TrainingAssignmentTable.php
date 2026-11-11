<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class TrainingAssignmentTable extends Component
{
    /**
     * The rows grouped into one section per training type.
     */
    public Collection $groups;

    /**
     * Create a new component instance.
     */
    public function __construct(public mixed $trainingAssignments)
    {
        // The query already orders by course seniority then request age, so
        // grouping here preserves that order and keeps the Blade file a pure
        // template. Grouping is per page: a type spanning a page boundary gets
        // its heading repeated on the next page, which is what we want.
        $this->groups = collect($trainingAssignments->items())
            ->groupBy(fn ($assignment) => $assignment->training_type->value);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.training-assignment-table');
    }
}
