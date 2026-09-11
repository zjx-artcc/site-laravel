@extends('layouts.admin')

@section('title', 'Event Management')

@section('body')
    <a href={{ route('admin.events.create') }} class='btn btn-primary mb-5'>+ New Event</a>
    @livewire('event-table', ['events' => $events])
@endsection
