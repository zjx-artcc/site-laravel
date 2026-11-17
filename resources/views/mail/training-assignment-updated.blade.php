@extends('mail.layout')

@section('heading', 'Training Assignment Updated')

@section('content')
    <p>Dear {{ $assignment->student->name }},</p>

    <p>Your training assignment for <strong>{{ $assignment->training_type->mapToString() }}</strong> has been updated:</p>

    <ul style="margin: 0 0 16px; padding-left: 20px;">
        <li><strong>Instructor:</strong> {{ $assignment->instructor?->name ?? 'Unassigned' }}</li>
        <li><strong>Status:</strong> {{ $assignment->status->label() }}</li>
    </ul>

    @if ($assignment->instructor != null)
        <p>If you haven't done so already, please contact your instructor via the Discord server within 7 days of this message, otherwise you risk removal from the training program. <strong>It is your responsibility to reach out to your instructor.</strong></p>
    @endif

    <p>For more information, please visit your <a href="{{ route('users.show', $assignment->student) }}" class="link" style="color: #0080C2;">profile</a>. If you have any questions about training, please contact the Training Administrator at <a href="mailto:zjx-ta@vatusa.net" class="link" style="color: #0080C2;">zjx-ta@vatusa.net</a>.</p>
@endsection
