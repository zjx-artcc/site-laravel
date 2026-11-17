@extends('mail.layout')

@section('heading', 'Solo Certification Issued')

@section('content')
    <p>Dear {{ $soloCert->user->name }},</p>

    <p>Congratulations! A solo certification has been issued by <strong>{{ $soloCert->issuedBy->name }}</strong> for <strong>{{ $soloCert->position }}</strong>. You can view it on <a href="{{ route('users.show.solo-certs', $soloCert) }}" class="link" style="color: #0080C2;">the website</a>.</p>

    <p>This solo certification is valid for 30 days and expires on <strong>{{ $soloCert->expires->format('Y-m-d') }}</strong>. Please review the training SOP regarding the restrictions related to solo certifications and contact your instructor or the Training Administrator at <a href="mailto:zjx-ta@vatusa.net" class="link" style="color: #0080C2;">zjx-ta@vatusa.net</a> if you have any questions.</p>
@endsection
