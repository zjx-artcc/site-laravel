@php
    use \App\Models\VisitorRequest;
    use \App\Enums\VisitRequestStatus;
    use \App\Models\Loa;
    use \App\Enums\LoaStatus;
    use \App\Models\StaffingRequest;
@endphp

<div class="navbar bg-info text-primary-content z-10">
    <div class="flex-1 ml-5">
        <a href='{{ route('admin.index') }}' class='text-xl'>Admin Actions</a>
    </div>

    <ul class='menu menu-horizontal items-center gap-x-5 justify-center'>
        @if(auth()->user()?->can('manage faqs'))
            <div class="">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <a href="{{ route('admin.faqs.index') }}">FAQs</a>
                </div>
            </div>
        @endif

        @if(auth()->user()?->hasRole('training'))
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Training Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('training-tickets.index') }}>Training Tickets</a></li>
                    <li><a href={{ route('training-assignments.index') }}>Training Assignments</a></li>
                    <li><a href={{ route('solo-certs.index') }}>Solo Certs</a></li>
                    @if(auth()->user()?->hasPermissionTo('certifications:write'))
                        <li><a href={{ route('certifications.index') }}>Certifications</a></li>
                    @endif
                </ul>
            </div>
        @endif

        @if(auth()->user()?->hasRole('facilities'))
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Data Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    @if(auth()->user()?->hasPermissionTo('manage statistics prefixes'))
                    <li><a href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a></li>
                    @endif

                    @if(auth()->user()?->hasPermissionTo('certification-facilities:write'))
                        <li><a href={{ route('certification-facilities.index') }}>Certification Facilities</a></li>
                    @endif
                    <li><a href="{{ route('admin.publications.index') }}">Document Management</a></li>
                </ul>
            </div>
        @endif

        @if(auth()->user()?->hasRole('events'))
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Events Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('admin.events.index') }}>Events</a></li>
                    <li><a href="{{ route('admin.events.position-presets.index') }}">Position Presets</a></li>
                    <li><a href="{{ route('admin.events.event-fields.index') }}">Event Field Presets</a></li>
                    @if(auth()->user()?->hasPermissionTo('staffing-requests:read'))
                        <li>
                            <a href={{ route('admin.staffing-requests.index') }}>Staffing Requests
                                @if(StaffingRequest::where('closed', false)->count() > 0)
                                    <span class='badge badge-primary'>{{ StaffingRequest::where('closed', false)->count() }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        @endif

        @if(auth()->user()?->hasRole('admin'))
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
                    <li><a href={{ route('statistics.quarterly') }}>Roster Purge Assistant</a></li>
                    <li>
                        <a href={{ route('loa.manage') }}>LOA Requests
                            @if(Loa::where('status', LoaStatus::PENDING)->count() > 0)
                                <span class='badge badge-primary'>{{ Loa::where('status', LoaStatus::PENDING)->count() }}</span>
                            @endif
                        </a>
                    </li>
                    <li><a href="{{ route('admin.news.index') }}">News Management</a></li>
                    <li><a href={{ route('logs.index') }}>Audit Log</a></li>
                </ul>
            </div>
        @endif
    </ul>
</div>
