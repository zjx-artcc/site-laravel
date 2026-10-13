@extends('mail.layout')

@section('content')
    <p>Hello {{ $loa->user->first_name }},</p>

    <p>Your Leave of Absence request has been <strong>denied</strong>.</p>

    @if ($loa->response)
        <p><strong>Response from staff:</strong> {{ $loa->response }}</p>
    @endif

    <p>You can submit a new request on your <a style='color: blue; text-decoration: underline;' href="{{ route('users.show', $loa->user) }}">profile page.</a></p>
@endsection
