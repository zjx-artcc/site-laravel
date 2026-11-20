@extends('mail.layout')

@section('content')
    <p>Hello {{ $user->first_name }},</p>

    <p>You have been removed from the Virtual Jacksonville ARTCC roster.</p>

    <p>Reason: <strong>{{ $reason }}</strong></p>

    <p>If you have questions or wish to appeal this, please contact <strong><a href="mailto:{{ strtolower(config('app.vatusa_facility')) }}-atm@vatusa.net">{{ strtolower(config('app.vatusa_facility')) }}-atm@vatusa.net</a></strong></p>
@endsection
