@extends('mail.layout')

@section('content')
    <p>Hello {{ $staffingRequest->user->first_name }},</p>

    <p>Your staffing request for <strong>{{ $staffingRequest->name }}</strong> has been closed by our events team.</p>

    <p>If you still need staffing or have questions, please submit a new request or reach out to the events
    team.</p>
@endsection
