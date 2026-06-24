@extends('layouts.main')

@section('title', 'Edit User')

@section('body')
    <x-card-component title='Modify User Information'>
        <form action={{ route('users.update', $user) }} enctype="multipart/form-data" method='POST'>
            @csrf
            @method('PUT')

            <input hidden name='id' value='{{ $user->id }}'/>

            <div class="w-max mb-5">
                @if ($user->rostered && strcasecmp($user->facility, 'ZJX') == 0)
                    <h2 class='badge badge-lg badge-accent'>Home Controller</h2>
                @elseif ($user->rostered)
                    <h2 class='badge badge-lg badge-error'>Visiting Controller</h2>
                @else
                    <h2 class='text-lg'>Not Rostered</h2>
                @endif
            </div>

            <img class='col-span-2 border-2 w-24 h-24 sm:w-36 sm:h-36 mb-5 rounded-full' src="{{ asset($user->profile_image_route) }}" alt=""/>
            <input type="file" name="image" class="file-input file-input-bordered w-full max-w-xs mb-5" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                <a href="{{ route('users.show', $user) }}" class="link mb-3 sm:absolute sm:top-5 sm:right-5">View User</a>
                <x-label label='CID' :value="$user->id"/>
                <x-label label='Rating' :value="$user->rating->mapToString()"/>

                @if($user->rostered)
                    <x-label-slot label='Operating Initials'>
                        <input
                        type="text"
                        name='operatingInitials'
                        maxlength='2'
                        class='input input-md w-full'
                        @disabled(!auth()->user()->hasPermissionTo('manage users'))
                        value={{ old('operatingInitials', $user->operating_initials) }}>
                    </x-label-slot>

                    <x-label label='Member Since' :value='(new DateTime($user->joined_at))->format("M d Y")'/>
                @endif

                @unless(strcasecmp($user->facility, 'ZJX') == 0)
                    <x-label label='Home Division' :value='$user->division'/>
                    <x-label label='Home Subdivision' :value='$user->facility'/>
                @endunless
            </div>

            <div class='col-span-2'>
                <x-label-slot label='Biography'>
                    <textarea name='biography' class='textarea w-full resize-none h-30'>{{ old('biography', $user->biography) }}</textarea>
                </x-label-slot>
            </div>
            <button type="submit" class='btn btn-primary'>Submit Changes</button>
        </form>
    </x-card-component>
@endsection
