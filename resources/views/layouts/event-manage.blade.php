@extends('layouts.admin')

@section('title', 'Manage Event: '.$event->title)

@section('body')
    <div class="w-full">
        <div role="tablist" class="tabs tabs-lift mb-6">
            <a role="tab" href="{{ route('admin.events.manage', $event) }}"
               @class(['tab', 'tab-active' => request()->routeIs('admin.events.manage')])>Overview</a>

            <a role="tab" href="{{ route('admin.events.edit', $event) }}"
               @class(['tab', 'tab-active' => request()->routeIs('admin.events.edit')])>General</a>

            @if ($positionsAccess !== 'hidden')
                <a role="tab" href="{{ route('admin.events.positions', $event) }}"
                   @class(['tab', 'tab-active' => request()->routeIs('admin.events.positions')])>
                    Positions
                    @if ($positionsAccess === 'readonly')
                        <x-locked-badge tip="You lack the 'assign event positions' permission. Positions are read-only." class="ml-1" />
                    @endif
                </a>
            @endif
        </div>

        @yield('event-content')
    </div>
@endsection
