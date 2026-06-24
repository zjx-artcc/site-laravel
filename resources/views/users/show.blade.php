@extends('layouts.profile')

@section('profile-content')
<div class='flex flex-col gap-3'>
    <x-card-component title='General Information'>
        <x-user-data :user="$user"/>
    </x-card-component>

    <x-card-component title='Statistics'>
        <div class="grid grid-cols-3 gap-4 border-b border-base-300 pb-4 mb-4 mt-2">
            <div class="text-center">
                <p class="text-xs text-base-content/60 mb-1">Total Hours</p>
                <p class="text-xl sm:text-2xl font-bold">{{ number_format($totalHours, 1) }}h</p>
            </div>
            <div class="text-center">
                <p class="text-xs text-base-content/60 mb-1">This Year</p>
                <p class="text-xl sm:text-2xl font-bold">{{ number_format($yearHours, 1) }}h</p>
            </div>
            <div class="text-center">
                <p class="text-xs text-base-content/60 mb-1">This Month</p>
                <p class="text-xl sm:text-2xl font-bold">{{ number_format($monthHours, 1) }}h</p>
            </div>
        </div>

        <p class="text-base font-semibold text-base-content/60 mb-3">Last 10 Sessions</p>

        @if($recentSessions->isEmpty())
            <p class='text-base'>No sessions recorded yet.</p>
        @else
            @php
                $facilityLabels = [2 => 'DEL', 3 => 'GND', 4 => 'TWR', 5 => 'TRC', 6 => 'CTR'];
            @endphp
            <div class='overflow-x-auto'>
                <table class='table table-zebra table-sm sm:table-md w-full border border-base-300'>
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap">Position</th>
                            <th class="whitespace-nowrap">Type</th>
                            <th class="whitespace-nowrap">Date</th>
                            <th class="text-right whitespace-nowrap">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentSessions as $session)
                            <tr>
                                <td class="font-mono whitespace-nowrap">{{ $session->callsign }}</td>
                                <td>{{ $facilityLabels[$session->facility_level] ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $session->start->format('d M Y') }}</td>
                                <td class="text-right">{{ number_format($session->durationHours(), 2) }}h</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card-component>
</div>
@endsection
