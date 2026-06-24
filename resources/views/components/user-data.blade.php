<div class="flex flex-wrap items-center gap-2 mb-5">
    @if ($user->rostered && strcasecmp($user->facility, 'ZJX') == 0)
        <h2 class='badge badge-lg badge-accent'>Home Controller</h2>
    @elseif ($user->rostered)
        <h2 class='badge badge-lg badge-error'>Visiting Controller</h2>
    @else
        <h2 class='text-lg'>Not Rostered</h2>
    @endif

    @foreach($user->staffRoles as $position)
        <h2 class='badge badge-lg badge-primary'>
            @if(!$position->primary_contact && ($position->title_short != 'MTR' && $position->title_short != 'INS'))
                Assistant
            @endif
            {{ $position->title_long }}
        </h2>
    @endforeach
</div>

<div class="relative">
    @if (auth()->user() && (auth()->user()->id == $user->id || auth()->user()->hasRole('admin')))
        <a href='{{ route('users.edit', $user) }}' class='link text-sm sm:absolute sm:top-0 sm:right-0 mb-3 sm:mb-0 inline-block'>Edit User</a>
    @endif

    <img class='border-2 rounded-full w-24 h-24 sm:w-36 sm:h-36 mb-5' src="{{ asset($user->profile_image_route) }}" alt=""/>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1">
        <x-label label='CID' :value="$user->id"/>
        <x-label label='Rating' :value="$user->rating->mapToString()"/>

        @if($user->rostered)
            <x-label label='Operating Initials' :value="$user->operating_initials"/>
        @endif

        @if($user->rostered && $user->joined_at != null)
            <x-label label='Member Since' :value='(new DateTime($user->joined_at))->format("M d Y")'/>
        @endif

        @unless(strcasecmp($user->facility, 'ZJX') == 0)
            <x-label label='Home Division' :value='$user->division'/>
            <x-label label='Home Subdivision' :value='$user->facility'/>
        @endunless

        @hasrole('staff')
            <x-label label="Email" :value="$user->email"/>
        @endhasrole

        <div class='sm:col-span-2'>
            <x-label-slot label='Biography'>
                <textarea class='textarea w-full resize-none h-30' disabled>{{ $user->biography }}</textarea>
            </x-label-slot>
        </div>
    </div>
</div>
