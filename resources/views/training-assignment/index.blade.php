@extends('layouts.admin')

@section('title', 'Training Assignments')

@section('body')
    {{-- One listener for the page, not one per row: `training-assignment-moved`
         is a window event, so twenty rows meant twenty handlers each calling
         reload() on the same signal. See TrainingAssignmentStatus::setStatus()
         for when it fires. --}}
    <div x-data @training-assignment-moved.window="window.location.reload()">
        {{-- Counted in the controller, not off the paginator: these describe the
             whole queue and stay put while you search or filter. --}}
        <div class="flex flex-row flex-wrap gap-x-6 gap-y-1 text-sm text-base-content/70 mb-3">
            <p>
                Total Requests:
                <span class="font-semibold text-base-content">{{ $pendingCount }}</span>
            </p>
            <p>
                Students In Training:
                <span class="font-semibold text-base-content">{{ $inTrainingCount }}</span>
            </p>
        </div>

        <x-search-training-assignments/>

        <x-training-assignment-table :training-assignments="$trainingAssignments"/>

        <div class="w-full max-w-150 mt-5">
            {{ $trainingAssignments->links() }}
        </div>
    </div>
@endsection
