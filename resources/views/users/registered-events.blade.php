@extends('layouts.profile')

@section('profile-content')
    <x-event-signups-profile-table
        :userId="$user->id"
        :registeredEvents="$registeredEvents"
    />
@endsection
