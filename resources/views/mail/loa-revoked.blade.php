@extends('mail.layout')

@section('heading', 'L.O.A. Revoked')

@section('content')
    <p>Dear {{ $loa->user->name }},</p>

    <p>Your approved L.O.A. has been <strong>revoked</strong> by staff, effective immediately.</p>

    @if ($loa->response)
        <p><strong>Response from staff:</strong> {{ $loa->response }}</p>
    @endif

    <p>If you have any questions, or need to submit a new request, please visit your <a href="{{ route('users.show', $loa->user) }}" class="link" style="color: #0080C2;">profile page</a>.</p>
@endsection
