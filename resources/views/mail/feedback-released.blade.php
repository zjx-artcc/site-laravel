@extends('mail.layout')

@section('content')
    <p>Hello {{ $feedback->user->first_name }},</p>

    <p>Your feedback about <strong>{{ $feedback->controller->name }}</strong> on <strong>{{ $feedback->position }}</strong> has been reviewed and released by our staff. It is now visible on the controller's profile.</p>

    <p>You can review your submitted feedback and any staff comments on the <a style='color: blue; text-decoration: underline;' href="{{ route('feedback.index') }}">feedback page</a>.</p>

    <p>Thank you for taking the time to share your experience!</p>
@endsection
