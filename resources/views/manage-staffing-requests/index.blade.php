@extends('layouts.admin')

@section('title', 'Staffing Requests')

@section('body')
    <x-search/>

    <p class="text-sm opacity-70 mt-2">Times are shown in UTC (Zulu).</p>

    @unless ($staffingRequests->isEmpty())
        <div class="overflow-x-auto">
            <table class='table table-zebra table-md w-max border-2 border-base-300 mt-5'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Submitted</th>
                        <th>Requested For</th>
                        <th>Submitted By</th>
                        <th>Event Name</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staffingRequests as $staffingRequest)
                        <tr>
                            <td>{{ $staffingRequest->id }}</td>
                            <td class='whitespace-nowrap'>
                                {{ $staffingRequest->created_at->utc()->format('Y-m-d H:i') }}Z
                            </td>
                            <td class='whitespace-nowrap'>
                                {{ $staffingRequest->requested_at->utc()->format('Y-m-d H:i') }}Z
                            </td>
                            <td>
                                <a href="{{ route('users.show', [$staffingRequest->user_id]) }}">
                                    {{ $staffingRequest->user->name }} - {{ $staffingRequest->user_id }}
                                </a>
                            </td>
                            <td>{{ $staffingRequest->name }}</td>
                            <td>
                                @if ($staffingRequest->closed)
                                    <span class="badge badge-ghost">Closed</span>
                                @else
                                    <span class="badge badge-success">Open</span>
                                @endif
                            </td>
                            <td class='whitespace-nowrap'>
                                {{ $staffingRequest->updated_at->utc()->format('Y-m-d H:i') }}Z
                            </td>
                            <td>
                                <div class='flex items-center gap-2'>
                                    <a href="{{ route('admin.staffing-requests.show', [$staffingRequest]) }}" class="btn btn-sm btn-primary">View</a>

                                    @haspermission('staffing-requests:write')
                                        @if ($staffingRequest->closed)
                                            <form action="{{ route('admin.staffing-requests.reopen', [$staffingRequest]) }}" method="POST">
                                                @method('PATCH')
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline">Reopen</button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.staffing-requests.close', [$staffingRequest]) }}" method="POST">
                                                @method('PATCH')
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-error">Close</button>
                                            </form>
                                        @endif
                                    @endhaspermission
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class='mt-5'>
            {{ $staffingRequests->appends(request()->query())->links() }}
        </div>
    @else
        <h1>No staffing requests found.</h1>
    @endunless
@endsection
