@extends('layouts.main')

@section('title', 'ARTCC Staff')

@section('body')
    <div class="w-full px-4 sm:px-6 lg:px-8">

        {{-- All Senior & Department Head Staff in one 6-card grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            <x-staff-card
                position="Air Traffic Manager (ATM)"
                description="The Air Traffic Manager oversees day-to-day operations and collaborates with all staff members of the ARTCC."
                :staff="$atm"
                reportsTo="VATUSA"
            />

            <x-staff-card
                position="Deputy Air Traffic Manager (DATM)"
                description="The Deputy Air Traffic Manager assists the Air Traffic Manager in overseeing the ARTCC. The Deputy Air Traffic Manager also oversees the Web, Events, and Facilities Department and assists as needed."
                :staff="$datm"
                reportsTo="Air Traffic Manager"
            />

            <x-staff-card
                position="Training Administrator (TA)"
                description="The Training Administrator is responsible for overseeing the training department of the ARTCC."
                :staff="$ta"
                reportsTo="Air Traffic Manager"
            />

            <x-staff-card
                position="Events Coordinator (EC)"
                description="The Events Coordinator manages the planning and execution of events related to vZJX or our neighbors."
                :staff="$ec"
                reportsTo="Deputy Air Traffic Manager"
            >
                <x-assistant-staff
                    positionTitle="Assistant Events Coordinators (AECs)"
                    :staff="$eventsTeam"
                />
            </x-staff-card>

            <x-staff-card
                position="Facility Engineer (FE)"
                description="The Facility Engineer is responsible for managing all data related to the VATSIM network, such as standard operating procedures and radar maps."
                :staff="$fe"
                reportsTo="Deputy Air Traffic Manager"
            >
                <x-assistant-staff
                    positionTitle="Assistant Facility Engineers (AFEs)"
                    :staff="$facilitiesTeam"
                />
            </x-staff-card>

            <x-staff-card
                position="Webmaster (WM)"
                description="The Webmaster handles all management of data systems in the vZJX network, including the website, relational databases, email systems, and more."
                :staff="$wm"
                reportsTo="Deputy Air Traffic Manager"
            >
                <x-assistant-staff
                    positionTitle="Assistant Webmasters (AWMs)"
                    :staff="$webTeam"
                />
            </x-staff-card>
        </div>

        {{-- Training Team --}}
        <div class="w-full">
            <x-card-component title="Training Team">
                <div class="flex flex-col gap-8">

                    {{-- Instructors --}}
                    <div>
                        <h2 class="text-2xl mb-3">Instructors</h2>
                        <div class="flex flex-row flex-wrap gap-x-4 gap-y-2">
                            @if (count($instructors) == 0)
                                <p class="text-lg">No instructors.</p>
                            @endif

                            @foreach($instructors as $mentor)
                                <a href="{{ route('users.show', ['user' => $mentor->user->id]) }}" class="text-lg hover:underline">
                                    {{ $mentor->user->first_name . ' ' . $mentor->user->last_name }}
                                    ({{ $mentor->user->rating->mapToString() }})
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Mentors --}}
                    <div>
                        <h2 class="text-2xl mb-3">Mentors</h2>
                        <div class="flex flex-row flex-wrap gap-x-4 gap-y-2">
                            @if (count($mentors) == 0)
                                <p class="text-lg">No mentors.</p>
                            @endif

                            @foreach($mentors as $mentor)
                                <a href="{{ route('users.show', ['user' => $mentor->user->id]) }}" class="text-lg hover:underline">
                                    {{ $mentor->user->first_name . ' ' . $mentor->user->last_name }}
                                    ({{ $mentor->user->rating->mapToString() }})
                                </a>
                            @endforeach
                        </div>
                    </div>

                </div>
            </x-card-component>
        </div>

    </div>
@endsection
