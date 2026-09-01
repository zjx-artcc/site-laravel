@extends('mail.layout')

@section('content')
    <p>Hello,</p>

    <p>You attempted to remove <strong>{{ $user->name }}</strong> ({{ $user->id }}) from the VATUSA roster, but the request was rejected by VATUSA.</p>

    <p>Reason submitted: <strong>{{ $reason }}</strong></p>

    <p>VATUSA's response: <strong>{{ $error }}</strong></p>

    <p>{{ $user->name }} has <strong>not</strong> been removed from the roster. You may need to try again or contact VATUSA if the problem persists.</p>
@endsection
