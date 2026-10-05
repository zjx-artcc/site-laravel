@extends('layouts.main')

@php
    use Stevebauman\Purify\Facades\Purify;
@endphp

@section('title', 'Home')
@section('hide-heading')@endsection

@section('body-nopad')
    <div
        class="hero min-h-[260px] h-[42vh] sm:h-[36vh] lg:h-[30vh] w-full bg-cover bg-center"
        style="background-image: url('{{ asset('images/hero_banner.jpg') }}')"
    >
        <div class="hero-overlay bg-black/50"></div>

        <div class="hero-content w-full max-w-none justify-start px-4 sm:px-6 lg:px-10">
            <div class="max-w-3xl text-left text-white">
                <h1 class="mb-3 text-base sm:text-lg lg:text-xl font-bold text-warning">
                    Virtual Jacksonville ARTCC
                </h1>

                <h1 class="mb-4 text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight">
                    Elevating Virtual Excellence.
                </h1>

                <p class="max-w-2xl text-sm sm:text-base">
                    The Virtual Jacksonville Air Route Traffic Control Community is dedicated to providing exceptional
                    services to all its esteemed guests and controllers.
                </p>
            </div>
        </div>
    </div>
@endsection

@section('body')
    <div
        class="pt-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)_minmax(0,24rem)] xl:grid-cols-[24rem_minmax(0,1fr)_24rem] lg:gap-4 items-start"
    >

        <aside class="order-2 lg:order-1 flex flex-col gap-4 w-full min-w-0">

            @if(count($soloCerts) > 0)
                <x-card-component title="Solo Certs">
                    <ul>
                        @foreach ($soloCerts as $soloCert)
                            <x-solo-cert-card :soloCert="$soloCert"/>
                        @endforeach
                    </ul>
                </x-card-component>
            @endif

            <div
                class="bg-base-100 rounded-md p-4 sm:p-6 w-full border-2 border-base-300 hover:shadow-2xl transition-all duration-300"
            >
                <h2 class="text-xl sm:text-2xl font-light mb-3">
                    Quick Links
                </h2>

                <div class="divide-y divide-base-300">
                    <div class="py-3">
                        <h3 class="font-bold text-primary dark:text-base-content">
                            <a href="https://discord.gg/bHDwSQn9fh">
                                <i class="fa-brands fa-discord"></i> Join Our Discord
                            </a>
                        </h3>
                    </div>

                    <div class="py-3">
                        <h3 class="font-bold text-primary dark:text-base-content">
                            <a href="{{ route('events.index') }}">
                                <i class="fa-regular fa-calendar"></i> View Events
                            </a>
                        </h3>
                    </div>

                    <div class="py-3">
                        <h3 class="font-bold text-primary dark:text-base-content">
                            <a href="{{ route('roster.index') }}">
                                <i class="fa-solid fa-users"></i> Controller Roster
                            </a>
                        </h3>
                    </div>

                    <div class="py-3">
                        <h3 class="font-bold text-primary dark:text-base-content">
                            <a href="{{ route('publications.index') }}">
                                <i class="fa-regular fa-file-lines"></i> Publications &amp; Downloads
                            </a>
                        </h3>
                    </div>

                    <div class="py-3">
                        <h3 class="font-bold text-primary dark:text-base-content">
                            <a href="{{ route('faq.index') }}">
                                <i class="fa-regular fa-circle-question"></i> FAQ &amp; Useful Controller Information
                            </a>
                        </h3>
                    </div>
                </div>
            </div>

            <x-card-component-2 title="Upcoming Events">
                <div class="flex justify-between">
                    @if(!$events->isEmpty())
                        <h1 class="font-bold text-base-content">
                            <a href="{{ route('events.index') }}">View All Events</a>
                        </h1>
                    @endif
                </div>

                @if($events->isEmpty())
                    <h1 class="text-lg">No upcoming events.</h1>
                @else
                    @foreach($events as $event)
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
            </x-card-component-2>

            <x-card-component-2 title="News">
                @if($news->isEmpty())
                    <h1 class="text-lg">Nothing new has been announced.</h1>
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
            </x-card-component-2>
        </aside>

        <main class="order-1 lg:order-2 w-full min-w-0">
            <x-card-component title="Active Center Split">
                <div class="w-full min-w-0 overflow-x-auto">
                    <livewire:sector-map/>
                </div>
            </x-card-component>
        </main>

        <aside class="order-3 lg:order-3 flex flex-col w-full min-w-0 gap-y-3">
            <x-card-component-2 title="Online Controllers">
                @unless(sizeof($onlineSessions) == 0)
                    @foreach ($onlineSessions as $session)
                        <x-online-controller
                            :callsign="$session->callsign"
                            :user="$session->user"
                            :userId="$session->user_id"
                            :onlineSince="new DateTime($session->start)"
                        />
                    @endforeach
                @else
                    <h1 class="text-lg">No controllers online.</h1>
                @endunless
            </x-card-component-2>

            <x-card-component-2 title="Controller Schedule">
                <livewire:controller-schedule/>

                <x-atc-booking-form/>
            </x-card-component-2>
        </aside>

    </div>
@endsection
