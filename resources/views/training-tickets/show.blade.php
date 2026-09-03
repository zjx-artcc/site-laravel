@extends($staffView ? 'layouts.admin' : 'layouts.main')

@section('title', 'Training Ticket - #'.$trainingTicket->id)

@section('body')
    <a href="{{ $staffView ? route('training-tickets.index') : route('users.show.training-tickets', $trainingTicket->user_id) }}" class="btn btn-ghost mb-4">&larr; Back</a>
    <div class="card card-body bg-base-300 w-full max-w-3xl">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
            <x-label label="Session Date" :value="$trainingTicket->session_start"/>
            <x-label label="Session Duration" :value="$trainingTicket->duration"/>
            <x-label label="Student" :value="$trainingTicket->student->first_name.' '.$trainingTicket->student->last_name"/>
            <x-label label="Instructor" :value="$trainingTicket->instructor->first_name.' '.$trainingTicket->instructor->last_name"/>
            <x-label label="Position" :value="$trainingTicket->position"/>
            <x-label-slot label="Score">
                <x-rating-readonly :rating="$trainingTicket->score"/>
            </x-label-slot>

            @if($trainingTicket->issuedCertificationLevel)
                <x-label-slot label="Certification Pushed">
                    <span class="badge badge-success badge-lg">
                        {{ $trainingTicket->issuedCertificationLevel->facility?->identifier }}
                        {{ $trainingTicket->issuedCertificationLevel->name }}
                        ({{ $trainingTicket->issuedCertificationLevel->abbreviation }})
                    </span>
                </x-label-slot>
            @endif
        </div>
        <x-label-slot label="Session Notes">
            <div id="notes" class='bg-base-100 text-base-content p-2 rounded-md min-h-50 w-full'>{!! \Stevebauman\Purify\Facades\Purify::clean($trainingTicket->notes) !!}</div>
        </x-label-slot>
    </div>

    @if($staffView)
        <div class="card card-body bg-base-300 w-full max-w-3xl mt-6 border border-warning">
            <x-label-slot label="Instructor Notes (staff only)">
                <p class="text-sm opacity-70 -mt-1 mb-2">Visible only to training staff. The student never sees this.</p>
                <div class="bg-base-100 border border-base-300 rounded-md p-4 min-h-24 prose max-w-none">{!! $trainingTicket->instructor_notes ? \Stevebauman\Purify\Facades\Purify::clean($trainingTicket->instructor_notes) : 'No instructor notes.' !!}</div>
            </x-label-slot>
        </div>
    @endif
@endsection
