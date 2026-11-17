@extends('mail.layout')

@section('heading', 'Welcome to vZJX')

@section('content')
    <p>Dear {{ $user->name }},</p>

    <p>Welcome to {{ config('app.vatusa_facility', 'ZJX') }}! We are happy to have you as part of the ARTCC.</p>

    <p>Please familiarize yourself with our code of conduct and training SOP, and visit <a href="{{ route('users.show', $user) }}" class="link" style="color: #0080C2;">your profile</a> when you are ready to make a training request.</p>
@endsection
