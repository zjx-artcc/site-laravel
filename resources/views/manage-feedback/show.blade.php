@extends('layouts.admin')

@section('title', 'Feedback #' . $feedback->id)

@section('body')
    <div class="max-w-5xl">
        {{-- Manage actions --}}
        <div class="flex flex-row flex-wrap items-center gap-2 mb-5">
            <a href="{{ route('admin.feedback.index') }}" class="btn btn-ghost">&larr; Back to Feedback</a>

            <div class="grow"></div>

            <span class="badge badge-lg
                @if ($feedback->status === \App\Enums\FeedbackStatus::RELEASED) badge-success
                @elseif ($feedback->status === \App\Enums\FeedbackStatus::STASHED) badge-error
                @else badge-neutral @endif">
                {{ $feedback->status->label() }}
            </span>
        </div>

        <div class="flex flex-col lg:flex-row items-start gap-5">
            <div class="w-full max-w-2xl">
                <x-card-component title="Feedback from {{ $feedback->user->name }}">
                    <p class="text-sm opacity-70 mb-4">
                        Submitted
                        <time data-local datetime="{{ $feedback->created_at->toIso8601String() }}">
                            {{ $feedback->created_at->timezone('America/New_York')->format('m-d-Y g:i A') }} ET
                        </time>
                    </p>

                    <div class="flex flex-col gap-y-4">
                        <div class="grid md:grid-cols-3 grid-cols-1 gap-4">
                            <label class="flex flex-col">
                                <span class="label-text font-semibold mb-1">Name</span>
                                <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $feedback->user->name }}" disabled>
                            </label>

                            <label class="flex flex-col">
                                <span class="label-text font-semibold mb-1">CID</span>
                                <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $feedback->user_id }}" disabled>
                            </label>

                            <label class="flex flex-col">
                                <a class="link" href="mailto:{{ $feedback->user->email }}">
                                    <span class="label-text font-semibold mb-1">Email</span>
                                </a>
                                <input type="email" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $feedback->user->email }}" disabled>
                            </label>
                        </div>

                        <div class="grid md:grid-cols-2 grid-cols-1 gap-4">
                            <label class="flex flex-col">
                                <span class="label-text font-semibold mb-1">Controller</span>
                                <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60"
                                       value="{{ $feedback->controller->name }} - {{ $feedback->controller_id }}" disabled>
                            </label>

                            <label class="flex flex-col">
                                <span class="label-text font-semibold mb-1">Position</span>
                                <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $feedback->position }}" disabled>
                            </label>
                        </div>

                        <label class="flex flex-col">
                            <span class="label-text font-semibold mb-1">Experience</span>
                            <input type="text" class="input input-bordered w-full bg-base-300 text-base-content/60" value="{{ $feedback->experience->value }}" disabled>
                        </label>

                        <label class="flex flex-col">
                            <span class="label-text font-semibold mb-1">Comments / Feedback</span>
                            <textarea rows="8" class="textarea textarea-bordered bg-base-300 text-base-content/60 w-full resize-none" disabled>{{ $feedback->comments }}</textarea>
                        </label>

                        <div class="flex items-center gap-x-3 w-max">
                            @if ($feedback->staff_followup)
                                <span class='text-success font-bold text-lg'>&check;</span>
                            @else
                                <span class='text-error font-bold text-lg'>&cross;</span>
                            @endif
                            <span>Staff follow-up requested</span>
                        </div>

                        @haspermission('feedback:write')
                        <div class="flex flex-row flex-wrap justify-end gap-2">
                            @if ($feedback->status === \App\Enums\FeedbackStatus::STASHED)
                                <form action="{{ route('admin.feedback.unstash', [$feedback]) }}" method="POST">
                                    @method('PUT')
                                    @csrf
                                    <button type="submit" class="btn btn-warning">Unstash</button>
                                </form>
                            @elseif ($feedback->status === \App\Enums\FeedbackStatus::PENDING)
                                <form action="{{ route('admin.feedback.stash', [$feedback]) }}" method="POST">
                                    @method('PUT')
                                    @csrf
                                    <button type="submit" class="btn btn-error">Stash</button>
                                </form>
                            @endif

                            @unless ($feedback->status === \App\Enums\FeedbackStatus::RELEASED)
                                <form action="{{ route('admin.feedback.release', [$feedback]) }}" method="POST">
                                    @method('PUT')
                                    @csrf
                                    <button type="submit" class="btn btn-success">Release</button>
                                </form>
                            @endunless
                        </div>
                        @endhaspermission
                    </div>
                </x-card-component>
            </div>

            <div class="w-full lg:w-96 lg:shrink-0">
                <x-card-component title="Staff Comments">
                    <div class="flex flex-col mt-2">
                        @forelse ($feedback->staffComments as $comment)
                            <div class="border-b border-base-300 py-3 last:border-b-0">
                                <div class="flex flex-row flex-wrap items-center gap-x-2 text-sm opacity-70">
                                    <time data-local datetime="{{ $comment->created_at->toIso8601String() }}">
                                        {{ $comment->created_at->timezone('America/New_York')->format('m-d-Y g:i A') }} ET
                                    </time>
                                    <span>&middot;</span>
                                    <a href="{{ route('users.show', [$comment->user_id]) }}" class="font-semibold link link-hover">
                                        {{ $comment->user->name }} - {{ $comment->user_id }}
                                    </a>
                                    @if ($comment->user_visible)
                                        <span class="badge badge-info badge-sm">Visible to submitter</span>
                                    @endif
                                </div>
                                <p class="mt-1 whitespace-pre-line">{{ $comment->comment }}</p>
                            </div>
                        @empty
                            <p class="opacity-70">No staff comments yet.</p>
                        @endforelse
                    </div>

                    @haspermission('feedback:write')
                    <form action="{{ route('admin.feedback.comments.store', [$feedback]) }}" method="POST" class="flex flex-col gap-y-2 mt-4">
                        @csrf
                        <textarea name="comment" rows="3" class="textarea textarea-bordered w-full resize-none"
                                  placeholder="Comment" required
                                  onkeydown="if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); this.form.requestSubmit(); }">{{ old('comment') }}</textarea>
                        @error('comment')
                            <span class="text-error text-sm">{{ $message }}</span>
                        @enderror
                        <label class="flex items-center gap-x-2 cursor-pointer w-max">
                            <input type="checkbox" name="user_visible" value="1" class="checkbox checkbox-sm" @checked(old('user_visible'))>
                            <span class="text-sm">Visible to submitter (sends an email notification)</span>
                        </label>
                        <span class="text-sm opacity-60">Press Enter to post &middot; Shift+Enter for a new line</span>
                    </form>
                    @endhaspermission
                </x-card-component>
            </div>
        </div>
    </div>
@endsection
