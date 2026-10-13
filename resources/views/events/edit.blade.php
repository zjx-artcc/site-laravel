@extends('layouts.event-manage')

@section('event-content')

    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ route('admin.events.update', ['event' => $event->id]) }}"
        class="w-full lg:w-1/2 mx-auto flex flex-col gap-5"
    >
        @csrf
        @method('PUT')

        <div class="card bg-base-100 border border-base-300 w-full">
            <div class="card-body">

                <h2 class="card-title">Basic Information</h2>

                <label class="label">Event Name</label>
                <input
                    name="title"
                    type="text"
                    required
                    class="input input-bordered w-full"
                    value="{{ old('title', $event->title) }}"
                />

                <h2 class="card-title mt-2">Description</h2>

                <x-markdown-editor name="description" :content="old('description', $event->description)" />

                <div class="flex gap-4 w-full">
                    <div class="flex-1">
                        <label class="label">Event Start (Zulu)</label>
                        <input
                            type="datetime-local"
                            name="start"
                            class="input input-bordered w-full"
                            required
                            value="{{ old('start', $event->start?->format('Y-m-d\TH:i')) }}"
                        >
                    </div>

                    <div class="flex-1">
                        <label class="label">Event End (Zulu)</label>
                        <input
                            type="datetime-local"
                            name="end"
                            class="input input-bordered w-full"
                            required
                            value="{{ old('end', $event->end?->format('Y-m-d\TH:i')) }}"
                        >
                    </div>
                </div>

                <div class="divider"></div>

                <div class="flex gap-4 w-full">
                    <div class="flex-1">
                        <h2 class="card-title">Event Type</h2>

                        <select name="type" class="select select-bordered w-full">
                            <option disabled {{ old('type', $event->type?->value) ? '' : 'selected' }}>
                                Select type
                            </option>

                            @foreach ($types as $t)
                                <option
                                    value="{{ $t->value }}"
                                    {{ old('type', $event->type?->value) === $t->value ? 'selected' : '' }}
                                >
                                    {{ str_replace('_', ' ', $t->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex-1">
                        @php
                            $featuredFieldItems = old('featured_fields') !== null
                                ? array_values(array_filter(array_map('trim', explode(',', old('featured_fields'))), 'strlen'))
                                : ($event->featured_fields ?? []);
                        @endphp

                        <x-list-editor
                            name="featured_fields"
                            label="Featured Fields"
                            placeholder="Eg. KMCO"
                            :items="$featuredFieldItems"
                        />
                    </div>
                </div>

                <div class="divider"></div>

                <div>
                    <h2 class="card-title">Banner Image</h2>

                    @if ($event->banner_url)
                        <img
                            src="{{ $event->banner_url }}"
                            alt="Current banner for {{ $event->title }}"
                            class="rounded-box max-h-48 w-auto mb-3"
                        />
                    @endif

                    <input
                        type="file"
                        name="image"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="file-input file-input-bordered w-full rounded-full max-w-xs mb-2"
                    />

                    <p class="text-sm opacity-70">
                        Leave blank to keep the current banner. JPEG, PNG, GIF, or WebP, up to 8 MB.
                    </p>
                </div>

                <div class="divider"></div>

                <p>
                    This event will be archived, not deleted, 24 hours after the published end date. This can be reverted
                    through the event manager.
                    Assign, publish, and unpublish individual positions through the Event Manager.
                    You can hide or un-hide this event from the events list among other common tasks.
                    You will be notified of any errors in your submission after you submit the form. All changes will be
                    saved as long as you dont leave this page or refresh.
                </p>

            </div>
        </div>

        <button class="btn btn-primary w-full mt-2" type="submit">
            Update Event
        </button>
    </form>

@endsection
