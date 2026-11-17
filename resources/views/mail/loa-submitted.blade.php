@extends('mail.layout')

@section('heading', 'L.O.A. Request Submitted')

@section('content')
    <p>Dear {{ $loa->user->name }},</p>

    <p>Your L.O.A. request from <strong>{{ $loa->start_date->format('Y-m-d') }}</strong> to <strong>{{ $loa->end_date->format('Y-m-d') }}</strong> has been submitted for review. Please allow up to 7 business days for review.</p>

    <p>You can monitor the status of your request on your <a href="{{ route('users.show', $loa->user) }}" class="link" style="color: #0080C2;">profile page</a>.</p>
@endsection
