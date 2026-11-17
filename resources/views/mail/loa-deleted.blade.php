@extends('mail.layout')

@section('heading', 'L.O.A. Request Deleted')

@section('content')
    <p>Dear {{ $loa->user->name }},</p>

    <p>Your L.O.A. request has been cancelled.</p>

    <p>You can submit a new request at any time on your <a href="{{ route('users.show', $loa->user) }}" class="link" style="color: #0080C2;">profile page</a>.</p>
@endsection
