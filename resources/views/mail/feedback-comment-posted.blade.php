@extends('mail.layout')

@section('heading', 'Update on Feedback Submitted to vZJX')

@section('content')
    <p>Dear {{ $comment->feedback->user->name }},</p>

    <p>A member of our staff has left a comment on the feedback you submitted about <strong>{{ $comment->feedback->controller->name }}</strong> on <strong>{{ $comment->feedback->position }}</strong>.</p>

    <p><strong>Staff comment:</strong><br><span style="white-space: pre-line;">{{ $comment->comment }}</span></p>

    <p>You can view your feedback and all staff comments on the <a href="{{ route('feedback.index') }}" class="link" style="color: #0080C2;">feedback page</a>.</p>
@endsection
