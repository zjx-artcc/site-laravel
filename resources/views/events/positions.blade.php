@extends('layouts.event-manage')

@section('event-content')
    <div class="w-full flex flex-col gap-5">
        @if ($positionsAccess === 'readonly')
            <div role="alert" class="alert alert-warning">
                <i class="fa-solid fa-lock"></i>
                <span>
                    You can view this event's positions but cannot edit them
                    (missing the <code>assign event positions</code> permission).
                </span>
            </div>
        @endif

        <div class="card bg-base-100 border border-base-300 w-full lg:w-1/2">
            <div class="card-body">
                <h2 class="card-title">Positions Locked</h2>

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
        </div>

        <div class="w-full lg:w-1/2">
            @livewire('event-positions-management', ['event' => $event, 'readOnly' => $positionsAccess === 'readonly'])
        </div>

        @livewire('event-registrants', ['event' => $event])
    </div>
@endsection
