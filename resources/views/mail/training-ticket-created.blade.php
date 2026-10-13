@extends('mail.layout')

@section('content')
    <p>Hello {{ $trainingTicket->student->first_name }},</p>
    
    <p>A training ticket has been filed by <strong>{{ $trainingTicket->instructor->name }}</strong>. You can view it on the website using <a style='color: blue; text-decoration: underline;' href="{{ route('training-tickets.show', $trainingTicket) }}">this link.</a></p>

    @if($trainingTicket->certificationIssuedLabel())
        <p><strong>{{ $trainingTicket->certificationIssuedLabel() }}</strong></p>
    @endif
@endsection