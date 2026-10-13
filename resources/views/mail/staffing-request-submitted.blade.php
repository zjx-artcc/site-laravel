@extends('mail.layout')

@section('content')
    <p>Hello {{ $staffingRequest->user->first_name }},</p>

    <p>Your staffing request for <strong>{{ $staffingRequest->name }}</strong> has been submitted. Our events team
    will follow up by email.</p>

    <p><strong>Requested For:</strong> {{ $staffingRequest->requested_at->utc()->format('Y-m-d H:i') }}Z</p>

    <p>Please allow up to 7 business days for a response.</p>
@endsection
