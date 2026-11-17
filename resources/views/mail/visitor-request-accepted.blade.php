@extends('mail.layout')

@section('heading', 'Visitor Application Accepted')

@section('content')
    <p>Dear {{ $visitorRequest->user->name }},</p>

    <p>Congratulations! Your visiting request to the Virtual Jacksonville ARTCC has been accepted.</p>

    <p>Your operating initials have been assigned as <strong>{{ $visitorRequest->user->operatingInitials }}</strong>.</p>

    <p>Please review the general operation, facility admin, and training policies prior to controlling. You can now view <a href="{{ route('users.show', $visitorRequest->user) }}" class="link" style="color: #0080C2;">your profile</a> on our website. Welcome to the ARTCC!</p>
@endsection
