@extends('layouts.main')


@section('body-nopad')
    <div
        class="hero h-[30vh] w-full bg-cover bg-center"
        style="background-image: url('{{ asset('images/fake_background.png') }}')"
    >
        <div class="hero-overlay bg-black/50"></div>

        <div class="hero-content w-full max-w-none justify-start px-10">
            <div class="max-w-3xl text-left text-white">
                <h1 class="mb-5 text-xl font-bold text-warning">Virtual Jacksonville ARTCC </h1>
                <h1 class="mb-5 text-5xl font-bold">Elevating Virtual Excellence.</h1>

                <p class="mb-5">
                    The Virtual Jacksonville Air Route Traffic Control Community is dedicated to providing exceptional
                    services to all its esteemed guests and controllers.
                </p>

            </div>
        </div>
    </div>
@endsection

@section('body')
    <div class="grid lg:grid-cols-3 md:grid-cols-2 grid-cols-1 gap-5 pt-5">
        @if(count($soloCerts) > 0)
            <x-card-component title="Solo Certs">
                <ul>
                    @foreach ($soloCerts as $soloCert)
                        <x-solo-cert-card :soloCert='$soloCert'/>
                    @endforeach
                </ul>
            </x-card-component>
        @endif
    </div>


    <div class="mt-8 flex gap-4">
        <div class="flex flex-col gap-4 w-full max-w-sm">
            <div class="flex flex-col gap-4 w-full max-w-sm">
                <div
                    class="bg-base-100 rounded-md p-6 w-full border-2 border-base-300 hover:shadow-2xl transition-all duration-300">

                    <!-- Title -->
                    <h2 class="text-2xl font-light mb-3">
                        Quick Links
                    </h2>

                    <!-- List -->
                    <div class="divide-y divide-base-300">

                        <div class="py-3">
                            <h3 class="font-bold text-primary">
                                <a href="https://discord.gg/bHDwSQn9fh">
                                    <i class="fa-brands fa-discord"></i> Join Our Discord
                                </a>
                            </h3>
                        </div>

                        <div class="py-3">
                            <h3 class="font-bold text-primary">
                                Become a Member
                            </h3>
                        </div>

                        <div class="py-3">
                            <h3 class="font-bold text-primary">
                                View Events
                            </h3>
                        </div>

                        <div class="py-3">
                            <h3 class="font-bold text-primary">
                                Read FAQs
                            </h3>
                        </div>

                    </div>

                </div>

                <x-card-component-2 title="Upcoming Events">
                    <div class="flex justify-between">
                        @if(!$events->isEmpty())
                            <h1 class="font-bold text-primary"><a href="{{ route('events.index') }}">View All Events</a>
                            </h1>
                        @endif
                    </div>

                    @if($events->isEmpty())
                        <h1 class="text-lg">No upcoming events.</h1>

                    @else

                        @foreach($events as $event)

                            <div class="flex flex-col my-2">
                                <div class="bg-base-300 rounded-xl shadow-sm overflow-hidden">
                                    <div class="flex">

                                        <!-- Date side -->
                                        <div
                                            class="bg-primary text-primary-content w-16 flex flex-col items-center justify-center py-2">
                                            <span
                                                class="text-sm font-semibold uppercase">{{ $event->start->format('M') }}</span>
                                            <span
                                                class="text-3xl font-bold leading-none">{{ $event->start->format('d') }}</span>
                                        </div>

                                        <!-- Event info -->
                                        <div class="p-3 flex-1">
                                            <h3 class="font-bold text-lg">
                                                {{ $event->title }}
                                            </h3>

                                            <p class="text-sm text-base-content/70 mt-1">
                                                {{$event->getFormattedTimeAttribute()}}
                                            </p>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                </x-card-component-2>

                <x-card-component-2 title="News">

                    @if($news->isEmpty())
                        <h1 class="text-lg">Nothing new has been announced.</h1>
                    @endif

                    <div class="">


                        @foreach($news as $n)

                            <div class="flex flex-col my-5">
                                <div
                                    class="bg-base-300 rounded-md overflow-hidden cursor-pointer hover:shadow-lg transition w-full"
                                    onclick="news_modal_{{ $n->id }}.showModal()"
                                >
                                    <div class="">
                                        <h3 class="font-medium text-lg text-black px-2">
                                            {{ $n->title }}
                                        </h3>
                                    </div>
                                </div>
                            </div>

                            <dialog id="news_modal_{{ $n->id }}" class="modal">
                                <div class="modal-box">

                                    <h3 class="text-xl font-bold">
                                        {{ $n->title }}
                                    </h3>

                                    <p class="text-md font-medium">
                                        {{ $n->published_at }}
                                    </p>

                                    <p class="py-4">
                                        {{ $n->content }}
                                    </p>

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
            </div>

        </div>

        <div class="w-full">
            <x-card-component title="Active Center Split">
                <!--Sector map will go here -->
            </x-card-component>
        </div>

        <div class="flex flex-col w-full max-w-sm gap-y-3">
            <div class="w-full max-w-sm">
                <x-card-component-2 title="Online Controllers">
                    @unless(sizeof($onlineSessions) == 0)
                        @foreach ($onlineSessions as $session)
                            <x-online-controller
                                :callsign='$session->callsign'
                                :user='$session->user'
                                :userId='$session->user_id'
                                :onlineSince='new DateTime($session->start)'/>
                        @endforeach
                    @else
                        <h1 class="text-lg">No controllers online.</h1>
                    @endunless
                </x-card-component-2>
            </div>


        </div>
    </div>
@endsection
