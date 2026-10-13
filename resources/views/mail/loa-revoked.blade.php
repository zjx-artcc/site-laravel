@extends('mail.layout')

@section('content')
    <p>Hello {{ $loa->user->first_name }},</p>

    <p>Your approved Leave of Absence has been <strong>revoked</strong> by staff, effective immediately.</p>

    @if ($loa->response)
        <p><strong>Response from staff:</strong> {{ $loa->response }}</p>
    @endif

    <p>If you have any questions, or need to submit a new request, please visit your <a style='color: blue; text-decoration: underline;' href="{{ route('users.show', $loa->user) }}">profile page.</a></p>
@endsection
