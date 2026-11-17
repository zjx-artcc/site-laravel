@extends('mail.layout')

@section('heading', 'L.O.A. Request Approved')

@section('content')
    <p>Dear {{ $loa->user->name }},</p>

    <p>Congratulations! Your L.O.A. request has been approved by staff from <strong>{{ $loa->start_date->format('Y-m-d') }}</strong> until <strong>{{ $loa->end_date->format('Y-m-d') }}</strong>.</p>

    @if ($loa->response)
        <p><strong>Response from staff:</strong> {{ $loa->response }}</p>
    @endif

    <p>You can view or modify your L.O.A. on your <a href="{{ route('users.show', $loa->user) }}" class="link" style="color: #0080C2;">profile page</a> at any time.</p>
@endsection
