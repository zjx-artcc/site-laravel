@extends('mail.layout')

@section('heading', 'Staffing Request Submitted')

@section('content')
    <p>Dear {{ $staffingRequest->user->name }},</p>

    <p>Your staffing request for <strong>{{ $staffingRequest->name }}</strong> on <strong>{{ $staffingRequest->requested_at->utc()->format('Y-m-d H:i') }}Z</strong> has been submitted. Our events team will follow up by email.</p>

    <p>Please allow up to 7 business days for a response. If you have any questions, please contact the events team at <a href="mailto:zjx-ec@vatusa.net" class="link" style="color: #0080C2;">zjx-ec@vatusa.net</a>.</p>
@endsection
