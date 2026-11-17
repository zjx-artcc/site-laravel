@extends('mail.layout')

@section('heading', 'New Feedback')

@section('content')
    <p>Dear {{ $feedback->controller->name }},</p>

    <p>Feedback about your session on <strong>{{ $feedback->position }}</strong> has just been released and is now visible on your controller profile.</p>

    <p><strong>Experience:</strong> {{ $feedback->experience->value }}</p>

    <p><strong>Comments:</strong><br><span style="white-space: pre-line;">{{ $feedback->comments }}</span></p>

    <p>You can view all of your released feedback on <a href="{{ route('users.show.feedback', [$feedback->controller_id]) }}" class="link" style="color: #0080C2;">your profile</a>. Keep up the great work!</p>
@endsection
