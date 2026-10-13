@extends('mail.layout')

@section('content')
    <p>Hello {{ $position->user->first_name }},</p>

    @if ($isUpdate)
        <p>Your position assignment for <strong>{{ $position->event->title }}</strong> has been updated:</p>
    @else
        <p>You have been assigned a position for <strong>{{ $position->event->title }}</strong>:</p>
    @endif

    <p><strong>Event: </strong>{{ $position->event->formatted_range }}</p>
    <p><strong>Assigned Position: </strong>{{ $position->assigned_position }}</p>
    <p><strong>Assigned Time: </strong>
        {{ $position->assigned_start?->utc()->format('m/d/Y H:i') ?? 'TBD' }}z
        -
        {{ $position->assigned_end?->utc()->format('H:i') ?? 'TBD' }}z
    </p>

    <p>For more information, please visit your <a href="{{ route('users.show.registered-events', $position->user) }}" style='color: blue; text-decoration: underline;'>profile</a>.</p>
@endsection
