@extends('mail.layout')

@section('content')
    <p>Hello {{ $loa->user->first_name }},</p>

    <p>Your Leave of Absence request has been cancelled.</p>

    <p>You can submit a new request at any time on your <a style='color: blue; text-decoration: underline;' href="{{ route('users.show', $loa->user) }}">profile page.</a></p>
@endsection
