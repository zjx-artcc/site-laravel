@extends('mail.layout')

@section('heading', 'Training Request Received')

@section('content')
    <p>Dear {{ $trainingAssignment->student->name }},</p>

    <p>Your training request for <strong>{{ $trainingAssignment->training_type->mapToString() }}</strong> has been received.</p>

    <p>To view your position in the training queue and other relevant information, please visit your <a href="{{ route('users.show.training-assignments', $trainingAssignment->student) }}" class="link" style="color: #0080C2;">profile</a>.</p>

    <p>If you have any questions about training, please contact the Training Administrator at <a href="mailto:zjx-ta@vatusa.net" class="link" style="color: #0080C2;">zjx-ta@vatusa.net</a>.</p>
@endsection
