@extends('layouts.admin-2')

@php
    use \App\Models\VisitorRequest;
    use \App\Enums\VisitRequestStatus;
    use \App\Models\Loa;
    use \App\Enums\LoaStatus;
    use \App\Models\StaffingRequest;
@endphp

@section('content')
    <section class="grid grid-cols-5 gap-3">
        <div class="col-span-full">
            <h1 class="col-span-full">Welcome back, User!</h1>
            <p>Here's what's going on in vZJX today.</p>
        </div>

        <x-card-component title="Total Rostered Users">
            <p>Hello World</p>
        </x-card-component>

        <x-card-component title="Online Controllers">
            <p>Hello World</p>
        </x-card-component>

        <x-card-component title="Events This Month">
            <p>Hello World</p>
        </x-card-component>

        <x-card-component title="Training Assignments">
            <p>Hello World</p>
        </x-card-component>

        <x-card-component title="Training Requests">
            <p>Hello World</p>
        </x-card-component>

        <x-card-component class="col-span-3" title="Active Center Split">
            <div class="w-full min-w-0 overflow-x-auto">
                <livewire:sector-map/>
            </div>
        </x-card-component>

        <x-card-component class="col-span-1" title="Upcoming Events">
            <p>Hello World</p>
        </x-card-component>

        <x-card-component class="col-span-1" title="Latest Announcements">
            <p>Hello World</p>
        </x-card-component>

    </section>
@endsection
