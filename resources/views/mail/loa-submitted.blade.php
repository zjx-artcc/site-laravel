@extends('mail.layout')

@section('content')
    <p>Hello {{ $loa->user->first_name }},</p>

    <p>Your Leave of Absence request has been submitted for review. You can monitor the status of your request on your <a class="text-primary underline" href="{{ route('users.show', $loa->user) }}">profile page.</a></p>

    <p><strong>Start Date:</strong> {{ $loa->start_date->format('Y-m-d') }}<br>
    <strong>End Date:</strong> {{ $loa->end_date->format('Y-m-d') }}</p>

    <p>Please allow up to 7 business days for review.</p>
@endsection
