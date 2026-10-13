<div class="dropdown dropdown-end">
    <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
        <span>Events</span>
        <x-dropdown-icon/>
    </div>
    <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
        <li><a href="{{ route('events.index') }}">Upcoming Events</a></li>
    </ul>
</div>

<div class="dropdown dropdown-end">
    <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
        <span>Controllers</span>
        <x-dropdown-icon/>
    </div>
    <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
        <li><a href="{{ route('visit.index') }}">Visit vZJX</a></li>
        <li><a href="{{ route('roster.index') }}">Roster</a></li>
        <li><a href="{{ route('staff.index') }}">Facility Staff</a></li>
    </ul>
</div>

@if(auth()->user()?->hasRole('staff'))
    <div class="">
        <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
            <a href="{{ route('admin.index') }}">Facility Admin</a>
        </div>
    </div>
@endif
@if(auth()->user())
    <div class="dropdown dropdown-end">
        <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
            <span>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }} - {{ auth()->user()->id }}</span>
            <x-dropdown-icon/>
        </div>
        <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
            <li><a href="{{ route('users.show', [auth()->user()->id]) }}">Profile</a></li>
            <li><a href="{{ route('auth.logout') }}">Logout</a></li>
        </ul>
    </div>
@else
    <x-login-button />
@endif