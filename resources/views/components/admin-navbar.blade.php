@php
    use \App\Models\VisitorRequest;
    use \App\Enums\VisitRequestStatus;
@endphp

<div class="navbar bg-info text-primary-content z-10">
    <div class="flex-1 ml-5">
        <a href='{{ route('admin.index') }}' class='text-xl'>Admin Actions</a>
    </div>

    <ul class='menu menu-horizontal items-center gap-x-5 justify-center'>
        @hasrole('training')
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Training Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('training-tickets.index') }}>Training Tickets</a></li>
                    <li><a href={{ route('training-assignments.index') }}>Training Assignments</a></li>
                    <li><a href={{ route('solo-certs.index') }}>Solo Certs</a></li>
                </ul>
            </div>
        @endhasrole

        @hasrole('facilities')
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Data Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a></li>
                    <li><a href="{{ route('admin.publications.index') }}">Document Management</a></li>
                </ul>
            </div>
        @endhasrole

        @hasrole('events')
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Events Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('admin.events.index') }}>Events</a></li>
                    <li><a href="{{ route('admin.events.position-presets.index') }}">Position Presets</a></li>
                    <li><a href="{{ route('admin.events.event-fields.index') }}">Event Field Presets</a></li>
                    <li><a href={{ route('admin.index') }}>Staffing Requests</a></li>
                </ul>
            </div>
        @endhasrole

        @hasrole('admin')
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Facility Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('admin.index') }}>Dashboard</a></li>
                    <li><a href={{ route('manage-users.index') }}>User Management</a></li>
                    <li>
                        <a href={{ route('visit.manage') }}>Visitor Requests
                            @if(VisitorRequest::where('status', VisitRequestStatus::PENDING)->count() > 0)
                                <span class='badge badge-primary'>{{ VisitorRequest::where('status', VisitRequestStatus::PENDING)->count() }}</span>
                            @endif
                        </a>
                    </li>
                    <li><a href="{{ route('admin.news.index') }}">News Management</a></li>
                    <li><a href={{ route('logs.index') }}>Audit Log</a></li>
                </ul>
            </div>
        @endhasrole
    </ul>
</div>
