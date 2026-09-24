@extends('mail.layout')

@section('content')
    <p>Hello {{ $user->first_name }},</p>

    <p>You have been removed from the Virtual Jacksonville ARTCC roster.</p>

    <p>Reason: <strong>{{ $reason }}</strong></p>

    <p>If you have questions or wish to appeal this, please contact <strong><a href="mailto:atm@zjxartcc.org">atm@zjxartcc.org</a></strong></p>
@endsection
