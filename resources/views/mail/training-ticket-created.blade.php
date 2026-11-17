@extends('mail.layout')

@section('heading', 'Training Ticket Created')

@section('content')
    <p>Dear {{ $trainingTicket->student->name }},</p>

    <p>A training ticket has been filed by <strong>{{ $trainingTicket->instructor->name }}</strong>. You can view it on <a href="{{ route('training-tickets.show', $trainingTicket) }}" class="link" style="color: #0080C2;">the website</a>.</p>

    @if ($trainingTicket->certificationIssuedLabel())
        <p><strong>{{ $trainingTicket->certificationIssuedLabel() }}</strong></p>
    @endif

    <p>If you have any questions about this ticket, please contact your instructor or the Training Administrator at <a href="mailto:zjx-ta@vatusa.net" class="link" style="color: #0080C2;">zjx-ta@vatusa.net</a>.</p>
@endsection
