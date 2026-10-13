@extends('layouts.admin')

@section('title', 'Training Assignments')

@section('body')
    <div>
        <form class="flex flex-col">
            <div class="flex flex-row gap-x-2 items-center mb-2">
                <label class="label" for="showInactive">Show Inactive Requests</label>
                <input
                    name="showInactive"
                    @if(request()->input('showInactive') == 'on')
                        checked
                    @endif
                    type="checkbox"
                />
            </div>

            <x-search-training-assignments/>
        </form>

        <x-training-assignment-table :training-assignments="$trainingAssignments"/>

        <div class="w-full max-w-150 mt-5">
            {{ $trainingAssignments->links() }}
        </div>
    </div>
@endsection
