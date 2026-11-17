@extends('mail.layout')

@section('heading', 'Visitor Application Rejected')

@section('content')
    <p>Dear {{ $visitorRequest->user->name }},</p>

    <p>Unfortunately, your visiting request to the Virtual Jacksonville ARTCC has been rejected.</p>

    <p><strong>Reason:</strong> {{ $visitorRequest->reason }}</p>

    <p>If you wish to appeal this, please contact <a href="mailto:zjx-atm@vatusa.net" class="link" style="color: #0080C2;">zjx-atm@vatusa.net</a>.</p>
@endsection
