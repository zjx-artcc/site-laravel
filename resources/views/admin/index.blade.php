@extends('layouts.admin')

@section('body')
    <div class='flex flex-wrap gap-10'>
        <x-card-component title="ARTCC Membership Overview">
            <div class="mt-5">
                <h2 class='text-2xl'><strong>Home:</strong> {{ $homeControllers }}</h2>
                <h2 class='text-2xl'><strong>Visiting:</strong> {{ $visitingControllers }}</h2>
                <h2 class='text-2xl'><strong>Total:</strong> {{ $homeControllers + $visitingControllers }}</h2>
            </div>
        </x-card-component>

        @role('training')
            <x-card-component title="Training Quick Links">
                <a class='btn btn-primary mt-5' href="{{ route('training-assignments.index') }}">My Students (TODO)</a>
                <a class='btn btn-primary' href="{{ route('training-assignments.index') }}">Training Assignments</a>
                <a class='btn btn-primary' href="{{ route('training-tickets.create') }}">Create Training Ticket</a>
                <a class='btn btn-primary' href="{{ route('training-tickets.create') }}">Issue Solo Cert</a>
            </x-card-component>
        @endrole

        @role('facilities')
            <x-card-component title="Facilities Quick Links">
                <a class='btn btn-primary mt-5' href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a>
                <a class='btn btn-primary' href="{{ route('admin.publications.index') }}">Document Management</a>
            </x-card-component>
        @endrole

        @can('manage statistics prefixes')
            <x-card-component title="Web Links">
                @if(session('success'))
                    <p class="text-success mt-4 text-sm">{{ session('success') }}</p>
                @endif
                @error('from')
                    <p class="text-error mt-4 text-sm">{{ $message }}</p>
                @enderror

                <form method="POST" action="{{ route('statistics.sync') }}" class="mt-5 space-y-3">
                    @csrf
                    @php
                        $months = range(1, 12);
                        $years  = range(2020, now()->year);
                    @endphp
                    <p class="text-sm font-semibold">Sync StatsSim Statistics</p>
                    <div class="flex flex-wrap gap-3 items-end">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-base-content/60">From</label>
                            <div class="flex gap-2">
                                <select name="from_year" class="select select-sm" style="min-width: 5.5rem">
                                    @foreach($years as $y)
                                        <option value="{{ $y }}" @selected(old('from_year', now()->subMonthNoOverflow()->year) == $y)>{{ $y }}</option>
                                    @endforeach
                                </select>
                                <select name="from_month" class="select select-sm">
                                    @foreach($months as $m)
                                        <option value="{{ $m }}" @selected(old('from_month', now()->subMonthNoOverflow()->month) == $m)>
                                            {{ \Illuminate\Support\Carbon::create(null, $m)->format('M') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-base-content/60">To</label>
                            <div class="flex gap-2">
                                <select name="to_year" class="select select-sm" style="min-width: 5.5rem">
                                    @foreach($years as $y)
                                        <option value="{{ $y }}" @selected(old('to_year', now()->year) == $y)>{{ $y }}</option>
                                    @endforeach
                                </select>
                                <select name="to_month" class="select select-sm">
                                    @foreach($months as $m)
                                        <option value="{{ $m }}" @selected(old('to_month', now()->month) == $m)>
                                            {{ \Illuminate\Support\Carbon::create(null, $m)->format('M') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Sync</button>
                    </div>
                </form>
            </x-card-component>
        @endcan

        @role('events')
            <x-card-component title="Events Quick Links">
                <a class='btn btn-primary mt-5' href="{{ route('admin.events.index') }}">Manage Events</a>
                <a class='btn btn-primary' href="{{ route('admin.events.position-presets.index') }}">Position Presets</a>
            </x-card-component>
        @endrole
    </div>
@endsection