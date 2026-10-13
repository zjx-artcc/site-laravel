@if(auth()->id() == $userId)
    @if($registeredEvents->isEmpty())
        <div>
            <strong>You don't have an active event signup. Check the events calendar to see what's upcoming.</strong>
        </div>
    @endif
@endif

<div class="overflow-x-auto">
<table class='table table-zebra table-md w-max mt-5'>
    <thead>
    <tr>
        <th class='py-3'>Event</th>
        <th class='py-3'>Position Requested</th>
        <th class='py-3'>Time Requested</th>
        <th class='py-3'>Final Assignment</th>
        <th class='py-3'>Final Start</th>
        <th class='py-3'>Final End</th>
    </tr>
    </thead>
    <tbody>
    @unless(sizeof($registeredEvents) == 0)
        @foreach ($registeredEvents as $registration)

            <tr>
                <td class='py-3'>
                    <a href="{{ route('events.show', $registration) }}" class="link link-primary">
                        {{ $registration->title }}
                    </a>
                </td>

                <td class='py-3'>{{ $registration->pivot->requested_position }}</td>

                <td class='py-3'>{{ $registration->getFormattedRangeAttribute() }}</td>

                @if($registration->published)
                    <td class='py-3'>{{ $registration->pivot->assigned_position }}</td>
                    <td class='py-3'>{{ $registration->pivot->assigned_start }}</td>
                    <td class='py-3'>{{ $registration->pivot->assigned_end }}</td>
                @else
                    <td class='py-3' colspan='3'>Position not yet published</td>
                @endif
            </tr>

        @endforeach
    @endunless
    </tbody>
</table>
</div>
