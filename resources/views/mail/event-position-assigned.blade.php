@extends('mail.layout')

@section('heading', $isUpdate ? 'Event Position Updated' : 'Event Position Assigned')

@section('content')
    <p>Dear {{ $position->user->name }},</p>

    @if ($isUpdate)
        <p>Your position assignment for <strong>{{ $position->event->title }}</strong> has been updated:</p>
    @else
        <p>You have been assigned a position for <strong>{{ $position->event->title }}</strong>:</p>
    @endif

    @php
        // Zulu then Eastern for a start/end pair. The end date is only repeated when the range
        // crosses midnight in that timezone. 'T' prints EST or EDT to match the date.
        $range = function ($start, $end, string $tz, string $time, string $suffix) {
            if (! $start || ! $end) {
                return 'TBD';
            }

            $start = $start->copy()->setTimezone($tz);
            $end = $end->copy()->setTimezone($tz);
            $endFormat = $start->isSameDay($end) ? $time : 'm/d/Y '.$time;

            return $start->format('m/d/Y '.$time).$suffix.' - '.$end->format($endFormat).$suffix;
        };
        $zulu = fn ($start, $end) => $range($start, $end, 'UTC', 'H:i', 'z');
        $eastern = fn ($start, $end) => $start && $end
            ? $range($start, $end, 'America/New_York', 'g:i A', '').' '.$start->copy()->setTimezone('America/New_York')->format('T')
            : null;
    @endphp

    <ul style="margin: 0 0 16px; padding-left: 20px;">
        <li>
            <strong>Event:</strong> {{ $zulu($position->event->start, $position->event->end) }}
            @if ($eastern($position->event->start, $position->event->end))
                <br>{{ $eastern($position->event->start, $position->event->end) }}
            @endif
        </li>
        <li><strong>Position:</strong> {{ $position->assigned_position }}</li>
        <li>
            <strong>Time:</strong> {{ $zulu($position->assigned_start, $position->assigned_end) }}
            @if ($eastern($position->assigned_start, $position->assigned_end))
                <br>{{ $eastern($position->assigned_start, $position->assigned_end) }}
            @endif
        </li>
    </ul>

    <p>For more information, please visit your <a href="{{ route('users.show.registered-events', $position->user) }}" class="link" style="color: #0080C2;">profile</a>. If you have any questions about the event, please contact the events team at <a href="mailto:zjx-ec@vatusa.net" class="link" style="color: #0080C2;">zjx-ec@vatusa.net</a>.</p>
@endsection
