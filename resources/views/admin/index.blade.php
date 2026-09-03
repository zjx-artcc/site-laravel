@extends('layouts.admin')

@php
    use \App\Models\VisitorRequest;
    use \App\Enums\VisitRequestStatus;
    use \App\Models\Loa;
    use \App\Enums\LoaStatus;
    use \App\Models\StaffingRequest;
@endphp

@section('body')
    <div class='flex flex-col gap-5 sm:flex-row sm:flex-wrap sm:gap-10'>
        <x-card-component title="ARTCC Membership Overview">
            <div class="mt-5">
                <h2 class='text-2xl'><strong>Home:</strong> {{ $homeControllers }}</h2>
                <h2 class='text-2xl'><strong>Visiting:</strong> {{ $visitingControllers }}</h2>
                <h2 class='text-2xl'><strong>Total:</strong> {{ $homeControllers + $visitingControllers }}</h2>
            </div>
        </x-card-component>

        @role('training')
            <x-card-component title="Training Quick Links">
                <a class='btn btn-primary mt-5' href="{{ route('admin.training.index') }}">Training Dashboard</a>
                <a class='btn btn-primary' href="{{ route('training-assignments.index') }}">My Students (TODO)</a>
                <a class='btn btn-primary' href="{{ route('training-assignments.index') }}">Training Assignments</a>
                <a class='btn btn-primary' href="{{ route('training-tickets.index') }}">Training Tickets</a>
                <a class='btn btn-primary' href="{{ route('training-tickets.create') }}">Create Training Ticket</a>
                <a class='btn btn-primary' href="{{ route('solo-certs.index') }}">Solo Certs</a>
                <a class='btn btn-primary' href="{{ route('solo-certs.create') }}">Issue Solo Cert</a>
                @haspermission('certifications:write')
                    <a class='btn btn-primary' href="{{ route('certifications.index') }}">Certifications</a>
                @endhaspermission
            </x-card-component>
        @endrole

        @if(auth()->user()?->can('manage contributors') || auth()->user()?->can('manage faqs'))
        <x-card-component title="Web Quick Links">
            @haspermission('manage contributors')
                <a class='btn btn-primary mt-5' href="{{ route('admin.contributors.index') }}">Manage Contributors</a>
            @endhaspermission
            @haspermission('manage faqs')
                <a class='btn btn-primary mt-5' href="{{ route('admin.faqs.index') }}">FAQ Management</a>
            @endhaspermission
        </x-card-component>
    @endif

    @role('facilities')
            <x-card-component title="Facilities Quick Links">
                @haspermission('manage statistics prefixes')
                    <a class='btn btn-primary mt-5' href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a>
                @endhaspermission
                @haspermission('certification-facilities:write')
                    <a class='btn btn-primary mt-5' href="{{ route('certification-facilities.index') }}">Certification Facilities Management</a>
                @endhaspermission
                @haspermission('documents:write')
                    <a class='btn btn-primary mt-5' href="{{ route('admin.publications.index') }}">Document Management</a>
                @endhaspermission
            </x-card-component>
    @endrole

    @role('events')
        @haspermission('manage events')
            <x-card-component title="Events Quick Links">
                <a class='btn btn-primary mt-5' href="{{ route('admin.events.index') }}">Manage Events</a>
                <a class='btn btn-primary' href="{{ route('admin.events.position-presets.index') }}">Position Presets</a>
                <a class='btn btn-primary' href="{{ route('admin.events.event-fields.index') }}">Event Field Presets</a>
                @haspermission('staffing-requests:read')
                    <a class='btn btn-primary' href="{{ route('admin.staffing-requests.index') }}">Staffing Requests
                        @if(StaffingRequest::where('closed', false)->count() > 0)
                            <span class='badge badge-primary'>{{ StaffingRequest::where('closed', false)->count() }}</span>
                        @endif
                    </a>
                @endhaspermission
            </x-card-component>
        @endhaspermission
    @endrole

    @role('admin')
        <x-card-component title="Facility Admin Quick Links">
            <a class='btn btn-primary mt-5' href="{{ route('manage-users.index') }}">User Management</a>
            <a class='btn btn-primary' href="{{ route('admin.news.index') }}">News Management</a>
            @haspermission('manage visiting controllers')
                <a class='btn btn-primary' href="{{ route('visit.manage') }}">Visitor Requests
                    @if(VisitorRequest::where('status', VisitRequestStatus::PENDING)->count() > 0)
                        <span class='badge badge-primary'>{{ VisitorRequest::where('status', VisitRequestStatus::PENDING)->count() }}</span>
                    @endif
                </a>
            @endhaspermission
            @haspermission('manage loas')
                <a class='btn btn-primary' href="{{ route('loa.manage') }}">LOA Requests
                    @if(Loa::where('status', LoaStatus::PENDING)->count() > 0)
                        <span class='badge badge-primary'>{{ Loa::where('status', LoaStatus::PENDING)->count() }}</span>
                    @endif
                </a>
            @endhaspermission
            @haspermission('view audit logs')
                <a class='btn btn-primary' href="{{ route('logs.index') }}">Audit Log</a>
            @endhaspermission
        </x-card-component>
    @endrole

    @haspermission('feedback:read')
        <x-card-component title="Admin Quick Links">
            <a class='btn btn-primary mt-5' href="{{ route('admin.feedback.index') }}">Manage Feedback</a>
        </x-card-component>
    @endhaspermission

    @haspermission('statistics:write')
        <x-card-component title="Manual Statistics Sync">
            <form method="POST" action="{{ route('statistics.sync') }}" class="mt-5 space-y-3">
                @csrf
                <p class="text-sm font-semibold">Sync VATSIM Core Statistics</p>
                <p class="text-sm text-base-content/60">The Core API reads all sessions in this date range and stores eligible rostered-controller sessions.</p>
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex flex-col gap-1">
                        <label for="statistics-from-date" class="text-xs text-base-content/60">From (UTC)</label>
                        <input id="statistics-from-date" name="from_date" type="date" class="input input-sm" value="{{ old('from_date', $statisticsSyncFromDate) }}" required>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="statistics-to-date" class="text-xs text-base-content/60">To (UTC)</label>
                        <input id="statistics-to-date" name="to_date" type="date" class="input input-sm" value="{{ old('to_date', $statisticsSyncToDate) }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Sync</button>
                </div>
            </form>
        </x-card-component>
    @endhaspermission
    </div>
@endsection
