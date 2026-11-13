@extends('layouts.profile')

@section('profile-content')
    @unless(is_null($noShowCount))
        <div class="alert {{ $noShowCount > 0 ? 'alert-warning' : '' }} mb-2">
            <span>No Shows: <strong>{{ $noShowCount }}</strong></span>
        </div>
    @endunless

    <x-training-ticket-table :trainingTickets="$trainingTickets"/>

    {{ $trainingTickets->links() }}
@endsection