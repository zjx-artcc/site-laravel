@extends('layouts.admin')

@php
    use \App\Models\VisitorRequest;
    use \App\Enums\VisitRequestStatus;
    use \App\Models\Loa;
    use \App\Enums\LoaStatus;
    use \App\Models\StaffingRequest;
@endphp

@section('content')
    <section class="grid grid-cols-10 gap-3 items-start">
        <div class="col-span-full">
            <h1 class="col-span-full text-2xl text-primary font-bold">Welcome back, {{ auth()->user()->first_name }}!</h1>
            <p class="">Here's what's going on in vZJX today.</p>
        </div>

        <x-card-component class="col-span-2" title="Total Rostered Users">
            <p class="text-2xl text-primary font-extrabold">{{ $totalRosteredUsers }}</p>
        </x-card-component>

        <x-card-component class="col-span-2" title="Online Controllers">
            <p class="text-2xl text-primary font-extrabold">{{ $onlineControllers }}</p>
        </x-card-component>

        <x-card-component class="col-span-2" title="Events This Month">
            <p class="text-2xl text-primary font-extrabold">{{ $eventsThisMonth }}</p>
        </x-card-component>

        <x-card-component class="col-span-2" title="Training Assignments">
            <p class="text-2xl text-primary font-extrabold">{{ $trainingAssignments }}</p>
        </x-card-component>

        <x-card-component class="col-span-2" title="Training Requests">
            <p class="text-2xl text-primary font-extrabold">{{ $trainingRequests }}</p>
        </x-card-component>

        <div class="col-span-6">
            <x-card-component class="" title="Active Center Split">
                <div class="w-full min-w-0 overflow-x-auto">
                    <livewire:sector-map/>
                </div>
            </x-card-component>
        </div>


        <x-card-component title="Upcoming Events" class="col-span-2">
            <div class="flex justify-between">
                @if(!$upcomingEvents->isEmpty())
                    <h1 class="font-bold text-base-content">
                        <a href="{{ route('events.index') }}">View All Events</a>
                    </h1>
                @endif
            </div>

            @if($upcomingEvents->isEmpty())
                <h1 class="text-lg">No upcoming events.</h1>
            @else
                @foreach($upcomingEvents as $event)
                    <a href="{{ route('events.show', $event->id) }}" class="flex flex-col my-2">
                        <div class="bg-base-300 rounded-xl shadow-sm overflow-hidden hover:shadow-lg transition">
                            <div class="flex">
                                <div
                                    class="bg-primary text-primary-content w-14 sm:w-16 shrink-0 flex flex-col items-center justify-center py-2"
                                >
                                        <span class="text-xs sm:text-sm font-semibold uppercase">
                                            {{ $event->start->format('M') }}
                                        </span>

                                    <span class="text-2xl sm:text-3xl font-bold leading-none">
                                            {{ $event->start->format('d') }}
                                        </span>
                                </div>

                                <div class="p-3 flex-1 min-w-0">
                                    <h3 class="font-bold text-base sm:text-lg truncate">
                                        {{ $event->title }}
                                        @if($event->isStartingSoon())
                                            <span class="badge badge-primary badge-xs ml-1">Starting Soon</span>
                                        @endif
                                    </h3>

                                    <p class="text-sm text-base-content/70 mt-1">
                                        {{ $event->getFormattedTimeAttribute() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            @endif
        </x-card-component>

        <x-card-component title="Latest Announcements" class="col-span-2">
            @if($news->isEmpty())
                <h1 class="text-lg">No recent announcements.</h1>
            @endif

            <div>
                @foreach($news as $n)
                    <div class="flex flex-col my-5">
                        <div
                            class="bg-base-300 rounded-md overflow-hidden cursor-pointer hover:shadow-lg transition w-full"
                            onclick="news_modal_{{ $n->id }}.showModal()"
                        >
                            <h3 class="font-medium text-base sm:text-lg text-base-content px-3 py-2">
                                {{ $n->title }}
                            </h3>
                        </div>
                    </div>

                    <dialog id="news_modal_{{ $n->id }}" class="modal">
                        <div class="modal-box w-11/12 max-w-2xl">
                            <h3 class="text-xl font-bold">
                                {{ $n->title }}
                            </h3>

                            <p class="text-md font-medium">
                                {{ $n->published_at }}
                            </p>

                            <div class="py-4">
                                {!! Purify::clean($n->content) !!}
                            </div>

                            <div class="modal-action">
                                <form method="dialog">
                                    <button class="btn">
                                        Close
                                    </button>
                                </form>
                            </div>
                        </div>
                    </dialog>
                @endforeach
            </div>
        </x-card-component>

    </section>
@endsection
