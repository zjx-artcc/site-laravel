@extends('layouts.admin')

@section('title', 'Visitor Requests')

@section('body')
    <x-search/>
    
    <div class='overflow-x-auto'>
    <table class='table table-zebra mt-5'>
        <thead>
            <tr>
                <td>Name</td>
                <td>Status</td>
                <td>Date Submitted</td>
                <td>Actions</td>
            </tr>
        </thead>
        <tbody>
            @foreach ($visitRequests as $request)
                <tr>
                    <td>
                        <a href="{{ route('users.show', ['user' => $request->user->id]) }}" class="text-base-content no-underline">
                            {{ $request->user->nameReversed }} ({{ $request->user->id }})
                        </a>
                    </td>
                    <td>
                        @if ($request->approved)
                            <span class="badge badge-success">Approved</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>

                    <td>
                        {{ $request->created_at->format('Y-m-d') }}
                    </td>
                    
                    <td>
                        <a class='link link-primary' href="{{ route('visit.show', $request->id) }}">View Request</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    {{ $visitRequests->links() }}
@endsection