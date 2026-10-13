@extends('layouts.admin')

@section('title', 'Staffing Request - ' . $staffingRequest->name)

@section('body')
    <div class="max-w-2xl">
        <div class="flex flex-row flex-wrap items-center gap-2 mb-5">
            <a href="{{ route('admin.staffing-requests.index') }}" class="btn btn-ghost">&larr; Back to Staffing Requests</a>
        </div>

        <x-card-component title="Staffing Request - {{ $staffingRequest->name }}">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                @if ($staffingRequest->closed)
                    <span class="badge badge-ghost">Closed</span>
                @else
                    <span class="badge badge-success">Open</span>
                @endif
                <span class="text-sm opacity-70">#{{ $staffingRequest->id }}</span>
            </div>

            <p class="text-sm opacity-70 mb-1">
                Submitted {{ $staffingRequest->created_at->utc()->format('Y-m-d H:i') }}Z
            </p>
            <p class="text-sm opacity-70 mb-1">
                Requested For {{ $staffingRequest->requested_at->utc()->format('Y-m-d H:i') }}Z
            </p>
            <p class="text-sm opacity-70 mb-4">
                Last Updated {{ $staffingRequest->updated_at->utc()->format('Y-m-d H:i') }}Z
            </p>

            <div class="flex flex-col gap-y-4">
                <div class="grid md:grid-cols-3 grid-cols-1 gap-4">
                    <label class="flex flex-col">
                        <span class="label-text font-semibold mb-1">Name</span>
                        <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $staffingRequest->user->name }}" disabled>
                    </label>

                    <label class="flex flex-col">
                        <span class="label-text font-semibold mb-1">CID</span>
                        <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $staffingRequest->user_id }}" disabled>
                    </label>

                    <label class="flex flex-col">
                        <a class="link" href="mailto:{{ $staffingRequest->user->email }}">
                            <span class="label-text font-semibold mb-1">Email</span>
                        </a>
                        <input type="email" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $staffingRequest->user->email }}" disabled>
                    </label>
                </div>

                <label class="flex flex-col">
                    <span class="label-text font-semibold mb-1">Event Name</span>
                    <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $staffingRequest->name }}" disabled>
                </label>

                <label class="flex flex-col">
                    <span class="label-text font-semibold mb-1">Description</span>
                    <textarea rows="8" class="textarea textarea-bordered bg-base-300 text-base-content/60 w-full resize-none" disabled>{{ $staffingRequest->description }}</textarea>
                </label>

                @haspermission('staffing-requests:write')
                    <div class="flex flex-col items-end gap-2">
                        @if ($staffingRequest->closed)
                            <form action="{{ route('admin.staffing-requests.reopen', [$staffingRequest]) }}" method="POST">
                                @method('PATCH')
                                @csrf
                                <button type="submit" class="btn btn-outline">Reopen Request</button>
                            </form>
                        @else
                            <form action="{{ route('admin.staffing-requests.close', [$staffingRequest]) }}" method="POST">
                                @method('PATCH')
                                @csrf
                                <button type="submit" class="btn btn-error">Close Request</button>
                            </form>
                            <p class="text-sm opacity-70">Closing will email the submitter and mark this request as
                                closed. It can be reopened later if needed. Create a new event
                                <a href="{{ route('admin.events.create', ['staffing_request' => $staffingRequest->id]) }}" target="_blank" class="link">here</a>,
                                prefilled with the details above.</p>
                        @endif
                    </div>
                @endhaspermission
            </div>
        </x-card-component>
    </div>
@endsection
