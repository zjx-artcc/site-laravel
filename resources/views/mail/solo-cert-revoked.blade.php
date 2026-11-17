@extends('mail.layout')

@section('heading', 'Solo Certification Revoked')

@section('content')
    <p>Dear {{ $soloCert->user->name }},</p>

    <p>Your solo certification for <strong>{{ $soloCert->position }}</strong> has been revoked. You are no longer authorized to control <strong>{{ $soloCert->position }}</strong> without an instructor present.</p>

    <p>Please contact your instructor or the Training Administrator at <a href="mailto:zjx-ta@vatusa.net" class="link" style="color: #0080C2;">zjx-ta@vatusa.net</a> for more information.</p>
@endsection
