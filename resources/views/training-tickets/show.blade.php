@extends(Auth::user()->hasRole('training') ? 'layouts.admin' : 'layouts.main')

@section('title', 'Training Ticket - #'.$trainingTicket->id)

@section('body')
    <a href="{{ Auth::user()->hasRole('training') ? route('training-tickets.index') : route('users.show.training-tickets', $trainingTicket->user_id) }}" class="btn btn-ghost mb-4">&larr; Back</a>
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
        </div>
        <x-label-slot label="Notes">
            <div id="notes" class='bg-white p-2 rounded-md min-h-50 w-full'>{!! $trainingTicket->notes !!}</div>
        </x-label-slot>
    </div>
@endsection

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quilljs-markdown@latest/dist/quilljs-markdown-common-style.css" />
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const quill = new Quill('#notes', {
            theme: 'snow',
            modules: {
                toolbar: false
            },
            readOnly: true
        });
    });
</script>