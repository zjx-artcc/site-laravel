@extends('mail.layout')

@section('content')
    <p>Hello {{ $comment->feedback->user->first_name }},</p>

    <p>A staff member has left a comment on the feedback you submitted about <strong>{{ $comment->feedback->controller->name }}</strong> on <strong>{{ $comment->feedback->position }}</strong>:</p>

    <p style="background-color: #F3F4F6; padding: 16px; border-radius: 0.25rem; white-space: pre-line;">{{ $comment->comment }}</p>

    <p>You can view your feedback and all staff comments on the <a style='color: blue; text-decoration: underline;' href="{{ route('feedback.index') }}">feedback page</a>.</p>
@endsection
