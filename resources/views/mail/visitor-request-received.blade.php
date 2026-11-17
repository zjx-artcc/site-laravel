@extends('mail.layout')

@section('heading', 'Visitor Application Received')

@section('content')
    <p>Dear {{ $visitorRequest->user->name }},</p>

    <p>Your visiting request to the Virtual Jacksonville ARTCC has been received. Please allow up to 5 business days for review.</p>

    <p>You can monitor the status of your request on the <a href="{{ route('visit.index') }}" class="link" style="color: #0080C2;">visiting page</a>.</p>
@endsection
