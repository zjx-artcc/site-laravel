@extends('layouts.event-manage')

@section('event-content')
    <div class="flex flex-col lg:flex-row gap-6 w-full">
        <div class="flex flex-col gap-10 flex-1 min-w-0 max-w-2xl">
            <div class="card bg-base-100 border border-base-300 w-full">
                <div class="card-body">

                <h2 class="card-title">
                    Event Overview
                </h2>

                <div class="divide-y divide-base-300">

                    <div class="py-3">
                        <h3 class="font-bold text-base-content">
                            Registered Controllers
                        </h3>
                        <p class="text-4xl">
                            {{ $registrants->count() }}
                        </p>
                    </div>


                    <div class="py-3">
                        <h3 class="font-bold text-base-content">
                            Most Requested Position
                        </h3>
                        <p class="text-4xl">
                            {{ $mostRequestedPosition ?? 'N/A' }}
                        </p>
                    </div>

                    <div class="py-3">
                        <h3 class="font-bold text-base-content">Visible</h3>
                        <form method="POST" action="{{ route('admin.event.visibility', $event) }}">
                            @csrf
                            @method('PATCH')

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="visible"
                                    class="toggle toggle-success toggle-xl"
                                    onchange="this.form.submit()"
                                    {{ !$event->hidden ? 'checked' : '' }}
                                    {{ $event->archived ? 'disabled' : '' }}
                                />
                            </label>
                        </form>
                    </div>

                    <div class="py-3">
                        <h3 class="font-bold text-base-content">
                            Positions Locked
                        </h3>
                        <form method="POST" action="{{ route('admin.event.positions-locked', $event) }}">
                            @csrf
                            @method('PATCH')

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="positions_locked"
                                    class="toggle toggle-success toggle-xl"
                                    onchange="this.form.submit()"
                                    {{ $event->positions_locked ? 'checked' : '' }}
                                />
                            </label>
                        </form>
                    </div>

                    <div class="py-3">
                        <h3 class="font-bold text-base-content">
                            Archived
                        </h3>
                        <form method="POST" action="{{ route('admin.event.archived', $event) }}">
                            @csrf
                            @method('PATCH')

                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="archived"
                                    class="toggle toggle-success toggle-xl"
                                    onchange="this.form.submit()"
                                    {{ $event->archived ? 'checked' : '' }}
                                />
                            </label>
                        </form>
                    </div>

                </div>

                </div>
            </div>

            <p>
                To view or assign this event's roster and positions, use the
                <a href="{{ route('admin.events.positions', $event) }}" class="link link-primary">Positions tab</a>.
            </p>
        </div>

        <div class="w-full lg:w-1/3 flex flex-col justify-start items-center gap-6">
            <div class="card card-dash bg-base-100 w-full max-w-xl shadow-sm overflow-hidden">
                @if ($event->banner_url)
                    <figure>
                        <img class='' src="{{ $event->banner_url }}" alt=""/>
                    </figure>
                @endif
                <div class="card-body bg-neutral">
                    <h1 class="card-title">
                        {{ $event->title }}
                        <div class="badge badge-secondary">{{ $event->type }}</div>
                    </h1>
                    <h2>
                        {{ $event->getFormattedRangeAttribute() }}
                    </h2>
                    @if ($event->featured_fields)
                        <p>{{ implode(', ', $event->featured_fields) }}</p>
                    @else
                        <p>No fields</p>
                    @endif
                    <br />
                    <div>{!! \Stevebauman\Purify\Facades\Purify::clean($event->description) !!}</div>
                </div>
            </div>
        </div>

    </div>
@endsection
