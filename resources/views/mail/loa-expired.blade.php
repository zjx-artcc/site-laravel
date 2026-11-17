@extends('mail.layout')

@section('heading', 'L.O.A. Expired')

@section('content')
    <p>Dear {{ $loa->user->name }},</p>

    <p>Your approved L.O.A. expired on <strong>{{ $loa->end_date->format('Y-m-d') }}</strong>. Welcome back!</p>

    <p>If you need additional time away, you can submit a new request on your <a href="{{ route('users.show', $loa->user) }}" class="link" style="color: #0080C2;">profile page</a>.</p>
@endsection
