@extends('layouts.admin')

@section('title', 'Training Assignments')

@section('body')
    <div>
        <x-search-training-assignments/>

        <x-training-assignment-table :training-assignments="$trainingAssignments"/>

        <div class="w-full max-w-150 mt-5">
            {{ $trainingAssignments->links() }}
        </div>
    </div>
@endsection
