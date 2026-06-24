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

                    @if(Auth::id() == $user->id || Auth::user()->hasRole('training'))
                    <a
                    role="tab"
                    href='{{ route("users.show.training-tickets", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.training-tickets')])
                    >Training Tickets</a>
                    <a
                    role="tab"
                    href='{{ route("users.show.training-assignments", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.training-assignments')])
                    >Training Assignments</a>
                    <a
                    role="tab"
                    href='{{ route("users.show.solo-certs", $user) }}'
                    @class(['tab whitespace-nowrap', 'tab-active' => request()->routeIs('users.show.solo-certs')])
                    >Solo Certs</a>
                    @endif
                </div>

            </div>

            <div class='w-full max-w-4xl mx-auto bg-base-100 border-base-300 border p-3 sm:p-4'>
                @yield('profile-content')
            </div>
@endsection
