@extends('mail.layout')

@section('content')
    <p>Hello {{ $loa->user->first_name }},</p>

    <p>Your Leave of Absence request has been <strong>approved</strong>.</p>

    <p><strong>Start Date:</strong> {{ $loa->start_date->format('Y-m-d') }}<br>
    <strong>End Date:</strong> {{ $loa->end_date->format('Y-m-d') }}</p>

    @if ($loa->response)
        <p><strong>Response from staff:</strong> {{ $loa->response }}</p>
    @endif

    <p>You can view or modify your request on your <a style='color: blue; text-decoration: underline;' href="{{ route('users.show', $loa->user) }}">profile page.</a></p>
@endsection
