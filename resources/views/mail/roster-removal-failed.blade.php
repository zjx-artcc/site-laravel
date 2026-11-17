@extends('mail.layout')

@section('heading', 'Roster Removal Failed')

@section('content')
    <p>Hello,</p>

    <p>You attempted to remove <strong>{{ $user->name }}</strong> ({{ $user->id }}) from the VATUSA roster, but the request was rejected by VATUSA.</p>

    <ul style="margin: 0 0 16px; padding-left: 20px;">
        <li><strong>Reason submitted:</strong> {{ $reason }}</li>
        <li><strong>VATUSA's response:</strong> {{ $error }}</li>
    </ul>

    <p>{{ $user->name }} has <strong>not</strong> been removed from the roster. You may need to try again or contact VATUSA if the problem persists.</p>
@endsection
