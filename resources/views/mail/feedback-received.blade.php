@extends('mail.layout')

@section('content')
    <p>Hello {{ $feedback->controller->first_name }},</p>

    <p>Feedback about your session on <strong>{{ $feedback->position }}</strong> has just been released and is now visible on your controller profile.</p>

    <p><strong>Experience:</strong> {{ $feedback->experience->value }}</p>

    <p style="background-color: #F3F4F6; padding: 16px; border-radius: 0.25rem; white-space: pre-line;">{{ $feedback->comments }}</p>

    <p>You can view all of your released feedback on <a style='color: blue; text-decoration: underline;' href="{{ route('users.show.feedback', [$feedback->controller_id]) }}">your profile</a>.</p>

    <p>Keep up the great work!</p>
@endsection2
