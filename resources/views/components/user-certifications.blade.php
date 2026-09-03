@props(['user'])

@php
    // Group the user's held certifications by facility, ordered by the facility's
    // display order. Each facility lists every level the user holds (highest first).
    $byFacility = $user->certifications
        ->filter(fn ($cert) => $cert->certificationLevel && $cert->certificationLevel->facility)
        ->groupBy(fn ($cert) => $cert->certificationLevel->facility_id)
        ->sortBy(fn ($certs) => $certs->first()->certificationLevel->facility->order);
@endphp

@if($byFacility->isEmpty())
    <p class='text-base-content/60'>This controller holds no certifications.</p>
@else
    <div class='flex flex-col gap-3'>
        @foreach($byFacility as $certs)
            @php
                $facility = $certs->first()->certificationLevel->facility;
                $levels = $certs->map->certificationLevel->sortByDesc('level');
            @endphp
            <div class='flex flex-row items-center gap-3'>
                <span class='font-semibold w-40 shrink-0'>{{ $facility->identifier }} &mdash; {{ $facility->name }}</span>
                <div class='flex flex-row flex-wrap gap-2'>
                    @foreach($levels as $level)
                        <span class='badge badge-success badge-md' title='{{ $level->name }}'>{{ $level->abbreviation }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
