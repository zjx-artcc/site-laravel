@extends('mail.layout')

@section('heading', 'Update on Feedback Submitted to vZJX')

@section('content')
    <p>Dear {{ $feedback->user->name }},</p>

    <p>Your feedback about <strong>{{ $feedback->controller->name }}</strong> on <strong>{{ $feedback->position }}</strong> has been reviewed and released by our staff. It is now visible on the controller's profile.</p>

    <p>You can review your submitted feedback and any staff comments on the <a href="{{ route('feedback.index') }}" class="link" style="color: #0080C2;">feedback page</a>.</p>

    <p>Thank you for taking the time to share your experience!</p>
@endsection
