@php
    use App\Enums\LoaStatus;
@endphp

@extends('layouts.admin')
@section('title', 'LOA Request - #'.$loa->id)

@section('body')
    <x-card-component>
        <div class='flex flex-col'>
            <a class='text-xl font-bold' href="{{ route('users.show', [$loa->user->id]) }}">{{ $loa->user->name }}</a>
            <br>

            <label class='label'>Status</label>
            @switch($loa->status)
                @case(LoaStatus::PENDING)
                    <p class='badge badge-warning'>Pending</p>
                    @break
                @case(LoaStatus::APPROVED)
                    <p class='badge badge-success'>Approved</p>
                    @break
                @case(LoaStatus::DENIED)
                    <p class='badge badge-error'>Denied</p>
                    @break
                @default
                    <p class='badge'>Inactive</p>
            @endswitch
            <br>

            <label class='label'>Date Submitted</label>
            <p>{{ $loa->created_at->format('Y-m-d') }}</p>

            <br>

            <label class='label'>Start Date</label>
            <p>{{ $loa->start_date->format('Y-m-d') }}</p>

            <br>

            <label class='label'>End Date</label>
            <p>{{ $loa->end_date->format('Y-m-d') }}</p>

            <br>

            <label class='label'>Reason for LOA</label>
            <p>{{ $loa->reason }}</p>

            @if ($loa->response)
                <br>
                <label class='label'>Response Sent to User</label>
                <p>{{ $loa->response }}</p>
            @endif

            @if ($loa->status === LoaStatus::PENDING)
                <form method="POST" class='flex flex-col mt-5'>
                    @csrf
                    @method('PUT')

                    <label for="response" class='label'>Response to User (optional)</label>
                    <textarea class='textarea textarea-bordered w-full max-w-120' name="response" id="response" rows="3" maxlength='1000'>{{ old('response', $loa->response) }}</textarea>

                    <div class='card-actions mt-2'>
                        <button type="submit" class='btn btn-success' formaction='{{ route('loa.approve', $loa->id) }}'>Approve Request</button>
                        <button type="submit" class='btn btn-error' formaction='{{ route('loa.deny', $loa->id) }}'>Deny Request</button>
                    </div>
                </form>
            @endif

            @if ($loa->status === LoaStatus::APPROVED)
                <form method="POST" class='flex flex-col mt-5'>
                    @csrf
                    @method('PUT')

                    <label for="response" class='label'>Response to User (optional)</label>
                    <textarea class='textarea textarea-bordered w-full max-w-120' name="response" id="response" rows="3" maxlength='1000'>{{ old('response', $loa->response) }}</textarea>

                    <div class='card-actions mt-2'>
                        <button type="submit" class='btn btn-error' formaction='{{ route('loa.revoke', $loa->id) }}'>Revoke LOA</button>
                    </div>
                </form>
            @endif
        </div>
    </x-card-component>
@endsection
