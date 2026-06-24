@extends('layouts.main')

@section('body')
    <div class="flex flex-col items-center gap-4 sm:gap-6 px-2 sm:px-0">
        {{-- Back button (mobile) --}}
        <div class="w-full max-w-xl sm:hidden">
            <a href="{{ route('events.index') }}" class="btn btn-ghost btn-sm gap-1 -ml-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Back to Events
            </a>
        </div>

        <div class="card card-dash bg-base-100 w-full max-w-xl shadow-sm">
            @if ($event->image_url)
            <figure>
                <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="w-full object-cover max-h-56 sm:max-h-72" />
            </figure>
            @endif
            <div class="card-body bg-neutral p-4 sm:p-6">
                <div class="flex flex-wrap items-start gap-2">
                    <h1 class="card-title text-lg sm:text-xl leading-snug">{{ $event->title }}</h1>
                    <div class="badge badge-secondary badge-sm sm:badge-md">{{ $event->type }}</div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 text-sm text-base-content/70 mt-1">
                    <div class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span>{{ $event->start->format('M j, Y g:i A') }}</span>
                    </div>
                    <span class="hidden sm:inline">–</span>
                    <span class="ml-5.5 sm:ml-0">{{ $event->end->format('M j, Y g:i A') }}</span>
                </div>

                @if ($event->featured_fields)
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($event->featured_fields as $field)
                            <span class="badge badge-outline badge-sm">{{ $field }}</span>
                        @endforeach
                    </div>
                @endif

                @if($event->description)
                    <div class="divider my-1"></div>
                    <p class="text-sm leading-relaxed">{{ $event->description }}</p>
                @endif
            </div>
        </div>

        @auth
        <div class="card bg-base-100 w-full max-w-xl shadow-sm">
            @livewire('event-registration', ['event' => $event])
        </div>
        @endauth
    </div>
@endsection
