@php
    use App\Enums\LoaStatus;
@endphp

@extends('layouts.profile')

@section('profile-content')
<div class='flex flex-col gap-2'>
    <x-card-component title='Leave of Absence'>
        @if ($activeLoa)
            <div class='flex flex-col gap-2'>
                <div>
                    <span class='label'>Status</span>
                    <br>
                    @switch($activeLoa->status)
                        @case(LoaStatus::PENDING)
                            <span class='badge badge-warning'>Pending</span>
                            @break
                        @case(LoaStatus::APPROVED)
                            <span class='badge badge-success'>Approved</span>
                            @break
                        @case(LoaStatus::DENIED)
                            <span class='badge badge-error'>Denied</span>
                            @break
                    @endswitch
                </div>

                @if ($activeLoa->status === LoaStatus::DENIED && $activeLoa->response)
                    <div>
                        <span class='label'>Response from staff</span>
                        <p>{{ $activeLoa->response }}</p>
                    </div>
                @endif

                <form method='POST' action="{{ route('loa.update', $activeLoa) }}" class='flex flex-col w-full max-w-120 gap-5 mt-2'>
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="start_date">Start Date</label>
                        <br>
                        <input type="date" id="start_date" name="start_date" class='input' value="{{ old('start_date', $activeLoa->start_date->format('Y-m-d')) }}">
                    </div>

                    <div>
                        <label for="end_date">End Date</label>
                        <br>
                        <input type="date" id="end_date" name="end_date" class='input' value="{{ old('end_date', $activeLoa->end_date->format('Y-m-d')) }}">
                    </div>

                    <div>
                        <label for="reason">Reason for LOA</label>
                        <br>
                        <textarea id="reason" name="reason" class="textarea textarea-bordered w-full" rows="4" maxlength='1000'>{{ old('reason', $activeLoa->reason) }}</textarea>
                    </div>

                    <p class='text-sm text-warning'>Saving changes will reset this LOA to Pending for staff review.</p>

                    <div class='flex gap-2'>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>

                <form method='POST' action="{{ route('loa.destroy', $activeLoa) }}" class='mt-2'>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-error">Cancel LOA</button>
                </form>
            </div>
        @else
            <p class='mb-4'>Your LOA will be submitted to staff for approval.</p>

            <form method='POST' action="{{ route('loa.store') }}" class='flex flex-col w-full max-w-120 gap-5'>
                @csrf

                <div>
                    <label for="start_date">Start Date</label>
                    <br>
                    <input type="date" id="start_date" name="start_date" class='input' value="{{ old('start_date') }}">
                </div>

                <div>
                    <label for="end_date">End Date</label>
                    <br>
                    <input type="date" id="end_date" name="end_date" class='input' value="{{ old('end_date') }}">
                </div>

                <div>
                    <label for="reason">Reason for LOA</label>
                    <br>
                    <textarea id="reason" name="reason" class="textarea textarea-bordered w-full" rows="4" maxlength='1000'>{{ old('reason') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">Submit LOA Request</button>
            </form>
        @endif
    </x-card-component>

    @if ($loaHistory->count() > 0)
        <x-card-component title='LOA History'>
            <div class='overflow-x-auto'>
            <table class='table table-zebra'>
                <thead>
                    <tr>
                        <td>Status</td>
                        <td>Start</td>
                        <td>End</td>
                        <td>Reason</td>
                        <td>Response</td>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($loaHistory as $loa)
                        <tr>
                            <td>{{ $loa->status->label() }}</td>
                            <td>{{ $loa->start_date->format('Y-m-d') }}</td>
                            <td>{{ $loa->end_date->format('Y-m-d') }}</td>
                            <td>{{ $loa->reason }}</td>
                            <td>{{ $loa->response ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            {{ $loaHistory->links() }}
        </x-card-component>
    @endif
</div>
@endsection
