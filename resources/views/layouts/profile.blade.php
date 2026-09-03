@extends('layouts.main')

@section('title', 'Profile - '.$user->name)

@section('body')
    <div class='w-full max-w-4xl mx-auto'>
        <div role="tablist" class="tabs tabs-lift overflow-x-auto flex-nowrap">
            <a
                role="tab"
                href='{{ route("users.show", $user) }}'
                @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show')])
            >General Info</a>

                    @auth
                    @php($isOwner = Auth::id() == $user->id)
                    @if($isOwner)
                    <a
                    role="tab"
                    href='{{ route("users.show.loa", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.loa')])
                    >LOA</a>
                    @endif
                    @if($isOwner || Auth::user()?->can('feedback:read'))
                    <a
                    role="tab"
                    href='{{ route("users.show.feedback", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.feedback')])
                    >Feedback</a>
                    @endif
                    @if($isOwner || Auth::user()?->can('training-tickets:read'))
                    <a
                    role="tab"
                    href='{{ route("users.show.training-tickets", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.training-tickets')])
                    >Training Tickets</a>
                    @endif
                    @if($isOwner || Auth::user()?->can('training-assignments:read'))
                    <a
                    role="tab"
                    href='{{ route("users.show.training-assignments", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.training-assignments')])
                    >Training Assignments</a>
                    @endif
                    @if($isOwner || Auth::user()?->can('solo-certs:read'))
                    <a
                    role="tab"
                    href='{{ route("users.show.solo-certs", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.solo-certs')])
                    >Solo Certs</a>
                    @endif
                    @endauth
            <a
                role="tab"
                href='{{ route("users.show.registered-events", $user) }}'
                @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.registered-events')])
            >Event Signups</a>
                </div>

                @auth
                    <p class="sm:hidden text-xs text-base-content/60 mt-1">Scroll to see more tabs &rarr;</p>
                @endauth
            </div>

    <div class='w-full max-w-4xl mx-auto bg-base-100 border-base-300 border p-3 sm:p-4'>
        @yield('profile-content')
    </div>
@endsection
