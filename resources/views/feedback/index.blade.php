@extends('layouts.main')

@section('title', 'Feedback')

@section('body')
    <div class="max-w-2xl">
        <x-card-component title="Submit Feedback">
            @hasrole('rostered')
            <p class="text-lg mt-2">Rostered controllers cannot submit feedback.</p>
            @else
            <p class="text-lg mb-4">Had a session with one of our controllers? Let us know how it went.</p>

            <form action="{{ route('feedback.store') }}" method="POST" class="flex flex-col gap-y-4">
                @csrf

                {{-- Autofilled submitter info --}}
                <div class="grid md:grid-cols-3 grid-cols-1 gap-4">
                    <label class="form-control flex flex-col">
                        <span class="label-text font-semibold mb-1">Your Name</span>
                        <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ auth()->user()->name }}" disabled>
                    </label>

                    <label class="form-control flex flex-col">
                        <span class="label-text font-semibold mb-1">CID</span>
                        <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ auth()->user()->id }}" disabled>
                    </label>

                    <label class="form-control flex flex-col">
                        <span class="label-text font-semibold mb-1">Email</span>
                        <input type="email" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ auth()->user()->email }}" disabled>
                    </label>
                </div>

                <div class="grid md:grid-cols-2 grid-cols-1 gap-4">
                    <label class="form-control flex flex-col">
                        <span class="label-text font-semibold mb-1">Controller</span>
                        <select name="controller_id" class="select select-bordered w-full" required>
                            <option value="" disabled @selected(old('controller_id') === null)>Select a controller</option>
                            @foreach ($controllers as $controller)
                                <option value="{{ $controller->id }}" @selected(old('controller_id') == $controller->id)>
                                    {{ $controller->name_reversed }} - {{ $controller->id }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="form-control flex flex-col">
                        <span class="label-text font-semibold mb-1">Position</span>
                        <input type="text" name="position" class="input input-bordered w-full" placeholder="e.g. JAX_APP"
                               value="{{ old('position') }}" required>
                    </label>
                </div>

                <label class="form-control flex flex-col">
                    <span class="label-text font-semibold mb-1">How was your experience?</span>
                    <select name="experience" class="select select-bordered w-full" required>
                        <option value="" disabled @selected(old('experience') === null)>Select a rating</option>
                        @foreach ($experiences as $experience)
                            <option value="{{ $experience->value }}" @selected(old('experience') === $experience->value)>{{ $experience->value }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control flex flex-col">
                    <span class="label-text font-semibold mb-1">Comments / Feedback</span>
                    <textarea name="comments" rows="5" class="textarea textarea-bordered w-full resize-none"
                              placeholder="Tell us about your experience" required>{{ old('comments') }}</textarea>
                </label>

                <label class="flex items-center gap-x-3 cursor-pointer w-max">
                    <input type="checkbox" name="staff_followup" value="1" class="checkbox" @checked(old('staff_followup'))>
                    <span>I would like a staff member to follow up with me</span>
                </label>

                <button type="submit" class="btn btn-primary w-max">Submit Feedback</button>
            </form>
            @endhasrole
        </x-card-component>

        @unless ($myFeedback->isEmpty())
            <x-card-component title="Your Feedback" class="mt-5">
                <div class="flex flex-col mt-2">
                    @foreach ($myFeedback as $entry)
                        <div class="border-b border-base-300 py-4 last:border-b-0">
                            <div class="flex flex-row flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="font-semibold">{{ $entry->controller->name }}</span>
                                <span class="font-mono">{{ $entry->position }}</span>
                                <span class="badge
                                    @if ($entry->status === \App\Enums\FeedbackStatus::RELEASED) badge-success
                                    @else badge-neutral @endif">
                                    {{ $entry->status === \App\Enums\FeedbackStatus::RELEASED ? 'Released' : 'Pending Review' }}
                                </span>
                                <span class="text-sm opacity-70">
                                    <time data-local datetime="{{ $entry->created_at->toIso8601String() }}">
                                        {{ $entry->created_at->timezone('America/New_York')->format('m-d-Y') }}
                                    </time>
                                </span>
                            </div>

                            <p class="mt-2 whitespace-pre-line">{{ $entry->comments }}</p>

                            @unless ($entry->visibleComments->isEmpty())
                                <div class="mt-3 pl-4 border-l-2 border-base-300 flex flex-col gap-y-3">
                                    @foreach ($entry->visibleComments as $comment)
                                        <div>
                                            <div class="flex flex-row flex-wrap items-center gap-x-2 text-sm opacity-70">
                                                <span class="font-semibold">Staff comment</span>
                                                <span>&middot;</span>
                                                <time data-local datetime="{{ $comment->created_at->toIso8601String() }}">
                                                    {{ $comment->created_at->timezone('America/New_York')->format('m-d-Y g:i A') }} ET
                                                </time>
                                            </div>
                                            <p class="mt-1 whitespace-pre-line">{{ $comment->comment }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </x-card-component>
        @endunless
    </div>
@endsection
