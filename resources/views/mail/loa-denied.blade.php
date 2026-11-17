@extends('mail.layout')

@section('heading', 'L.O.A. Request Denied')

@section('content')
    <p>Dear {{ $loa->user->name }},</p>

    <p>Unfortunately, your L.O.A. request has been <strong>denied</strong> by staff.</p>

    @if ($loa->response)
        <p><strong>Response from staff:</strong> {{ $loa->response }}</p>
    @endif

    <p>You can submit a new request on your <a href="{{ route('users.show', $loa->user) }}" class="link" style="color: #0080C2;">profile page</a>.</p>
@endsection
