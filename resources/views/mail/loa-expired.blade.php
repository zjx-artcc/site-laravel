@extends('mail.layout')

@section('content')
    <p>Hello {{ $loa->user->first_name }},</p>

    <p>Your approved Leave of Absence has expired as of {{ $loa->end_date->format('Y-m-d') }}. Welcome back!</p>

    <p>If you need additional time away, you can submit a new request on your <a style='color: blue; text-decoration: underline;' href="{{ route('users.show', $loa->user) }}">profile page.</a></p>
@endsection
