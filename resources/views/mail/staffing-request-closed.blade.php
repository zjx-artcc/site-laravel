@extends('mail.layout')

@section('heading', 'Staffing Request Closed')

@section('content')
    <p>Dear {{ $staffingRequest->user->name }},</p>

    <p>Your staffing request for <strong>{{ $staffingRequest->name }}</strong> has been closed by our events team.</p>

    <p>If you still need staffing or have questions, please submit a new request or reach out to the events team at <a href="mailto:zjx-ec@vatusa.net" class="link" style="color: #0080C2;">zjx-ec@vatusa.net</a>.</p>
@endsection
