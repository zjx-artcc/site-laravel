@extends('layouts.profile')

@section('profile-content')
    <x-card-component title='Feedback'>
        @if($releasedFeedback->isEmpty())
            <p class='text-base mt-2'>No feedback yet.</p>
        @else
            <div class='flex flex-col mt-2'>
                @foreach($releasedFeedback as $entry)
                    <div class='border-b border-base-300 py-4 last:border-b-0'>
                        <div class='flex flex-row flex-wrap items-center gap-x-3 gap-y-1'>
                            <span class='badge badge-neutral'>{{ $entry->experience->value }}</span>
                            <span class='font-mono font-semibold'>{{ $entry->position }}</span>
                            <span class='text-sm opacity-70'>
                                <time data-local datetime="{{ $entry->created_at->toIso8601String() }}">
                                    {{ $entry->created_at->timezone('America/New_York')->format('m-d-Y') }}
                                </time>
                            </span>
                        </div>
                        <p class='mt-2 whitespace-pre-line'>{{ $entry->comments }}</p>
                    </div>
                @endforeach
            </div>

            <div class='mt-3'>
                {{ $releasedFeedback->links() }}
            </div>
        @endif
    </x-card-component>
@endsection
