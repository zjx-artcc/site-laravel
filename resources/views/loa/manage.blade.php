@php
    use App\Enums\LoaStatus;
@endphp

@extends('layouts.admin')

@section('title', 'LOA Requests')

@section('body')
    @if($loas->isEmpty())
        <p class="mt-5">There are no LOA requests.</p>
    @else
        <div class='overflow-x-auto'>
        <table class='table table-zebra mt-5'>
            <thead>
                <tr>
                    <td>Name</td>
                    <td>Status</td>
                    <td>Start</td>
                    <td>End</td>
                    <td>Date Submitted</td>
                    <td>Response</td>
                    <td>Actions</td>
                </tr>
            </thead>
            <tbody>
                @foreach ($loas as $loa)
                    <tr>
                        <td>
                            <a href="{{ route('users.show', ['user' => $loa->user->id]) }}" class="text-base-content no-underline">
                                {{ $loa->user->nameReversed }} ({{ $loa->user->id }})
                            </a>
                        </td>
                        <td>
                            @switch($loa->status)
                                @case(LoaStatus::PENDING)
                                    <span class="badge badge-warning">Pending</span>
                                    @break
                                @case(LoaStatus::APPROVED)
                                    <span class="badge badge-success">Approved</span>
                                    @break
                                @case(LoaStatus::DENIED)
                                    <span class="badge badge-error">Denied</span>
                                    @break
                                @default
                                    <span class="badge">Inactive</span>
                            @endswitch
                        </td>
                        <td>{{ $loa->start_date->format('Y-m-d') }}</td>
                        <td>{{ $loa->end_date->format('Y-m-d') }}</td>
                        <td>{{ $loa->created_at->format('Y-m-d') }}</td>
                        <td>{{ $loa->response ?: '—' }}</td>
                        <td>
                            <a class='link link-primary' href="{{ route('loa.show', $loa->id) }}">View Request</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        {{ $loas->links() }}
    @endif
@endsection
