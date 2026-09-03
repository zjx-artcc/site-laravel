@extends('layouts.admin')

@section('title', 'Feedback Management')

@section('body')
    <x-search/>

    @unless ($feedback->isEmpty())
        <div class='overflow-x-auto'>
        <table class='table table-zebra table-md w-max border-2 border-base-300 mt-5'>
            <thead>
                <tr>
                    <th>Submitted</th>
                    <th>Submitted By</th>
                    <th>Controller</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Follow-up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($feedback as $entry)
                    <tr>
                        <td class='whitespace-nowrap'>
                            <time data-local datetime="{{ $entry->created_at->toIso8601String() }}">
                                {{ $entry->created_at->timezone('America/New_York')->format('m-d-Y g:i A') }} ET
                            </time>
                        </td>
                        <td>
                            <a href="{{ route('users.show', [$entry->user_id]) }}">
                                {{ $entry->user->name }} - {{ $entry->user_id }}
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('users.show', [$entry->controller_id]) }}">
                                {{ $entry->controller->name }} - {{ $entry->controller_id }}
                            </a>
                        </td>
                        <td>{{ $entry->position }}</td>
                        <td>
                            @if ($entry->status === \App\Enums\FeedbackStatus::STASHED)
                                <span class='text-error'>{{ $entry->status->label() }}</span>
                            @elseif ($entry->status === \App\Enums\FeedbackStatus::RELEASED)
                                <span class='text-success'>{{ $entry->status->label() }}</span>
                            @else
                                {{ $entry->status->label() }}
                            @endif
                        </td>
                        <td class='text-center'>
                            @if ($entry->staff_followup)
                                <span class='text-success font-bold text-lg'>&check;</span>
                            @else
                                <span class='text-error font-bold text-lg'>&cross;</span>
                            @endif
                        </td>
                        <td>
                            <div x-data="{
                                    open: false,
                                    top: 0,
                                    left: 0,
                                    openMenu() {
                                        const rect = $refs.trigger.getBoundingClientRect();
                                        this.top = rect.bottom + window.scrollY;
                                        this.left = rect.right + window.scrollX - 160;
                                        this.open = true;
                                    },
                                }">
                                <div x-ref="trigger" tabindex="0" role="button" class="btn btn-sm" @click="open ? (open = false) : openMenu()">Actions</div>

                                <template x-teleport="body">
                                    <ul x-show="open" x-cloak
                                        @click.outside="open = false"
                                        @click="open = false"
                                        :style="`top: ${top}px; left: ${left}px;`"
                                        class="fixed text-base-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-sm border border-base-300">
                                        <li><a href="{{ route('admin.feedback.show', [$entry]) }}">View</a></li>

                                        @haspermission('feedback:write')
                                        @if ($entry->status === \App\Enums\FeedbackStatus::STASHED)
                                            <li>
                                                <form action="{{ route('admin.feedback.unstash', [$entry]) }}" method="POST">
                                                    @method('PUT')
                                                    @csrf
                                                    <button type="submit">Unstash</button>
                                                </form>
                                            </li>
                                        @elseif ($entry->status === \App\Enums\FeedbackStatus::PENDING)
                                            <li>
                                                <form action="{{ route('admin.feedback.stash', [$entry]) }}" method="POST">
                                                    @method('PUT')
                                                    @csrf
                                                    <button type="submit">Stash</button>
                                                </form>
                                            </li>
                                        @endif

                                        @unless ($entry->status === \App\Enums\FeedbackStatus::RELEASED)
                                            <li>
                                                <form action="{{ route('admin.feedback.release', [$entry]) }}" method="POST">
                                                    @method('PUT')
                                                    @csrf
                                                    <button type="submit">Release</button>
                                                </form>
                                            </li>
                                        @endunless
                                        @endhaspermission
                                    </ul>
                                </template>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class='mt-5'>
            {{ $feedback->appends(request()->query())->links() }}
        </div>
    @else
        <h1>No feedback found.</h1>
    @endunless
@endsection
