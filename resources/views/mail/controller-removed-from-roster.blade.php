@extends('mail.layout')

@section('heading', 'Roster Status Change')

@section('content')
    <p>Dear {{ $user->name }},</p>

    <p>You have been removed from the Virtual Jacksonville ARTCC roster.</p>

    <p><strong>Reason:</strong> {{ $reason }}</p>

    <p>If you have questions or wish to appeal this, please contact <a href="mailto:zjx-atm@vatusa.net" class="link" style="color: #0080C2;">zjx-atm@vatusa.net</a>.</p>
@endsection
