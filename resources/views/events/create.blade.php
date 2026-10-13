@extends('layouts.admin')

@section('title', 'Create Event')

@section('body')

    <form
        method="POST"
        action="{{ route('admin.events.store') }}"
        enctype="multipart/form-data"
        class="w-full lg:w-1/2 mx-auto flex flex-col gap-5"
    >
        @csrf

        <div class="card bg-base-100 border border-base-300 w-full">
            <div class="card-body">
                    <h2 class="card-title">Basic Information</h2>

                    <label class="label">Event Name</label>
                    <input
                        name="title"
                        type="text"
                        class="input input-bordered w-full"
                        required
                        value="{{ old('title', $prefillTitle) }}"
                    />

                    <h2 class="card-title">Description</h2>

                    <x-markdown-editor name="description" :content="old('description', $prefillDescription)" />

                    <div class="flex gap-4 w-full">
                        <div class="flex-1">
                            <label class="label">Event Start (Zulu)</label>
                            <input
                                type="datetime-local"
                                name="start"
                                class="input input-bordered w-full"
                                required
                                value="{{ old('start') }}"
                            />
                        </div>

                        <div class="flex-1">
                            <label class="label">Event End (Zulu)</label>
                            <input
                                type="datetime-local"
                                name="end"
                                class="input input-bordered w-full"
                                required
                                value="{{ old('end') }}"
                            />
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="flex gap-4 w-full">
                        <div class="flex-1">
                            <h2 class="card-title">Event Type</h2>

                            <select
                                name="type"
                                class="select select-bordered w-full"
                            >
                                <option disabled selected>Select type</option>

                                @foreach ($types as $t)
                                    <option
                                        value="{{ $t->name }}"
                                        {{ old('type') == $t->name ? 'selected' : '' }}
                                    >
                                        {{ str_replace('_', ' ', $t->name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex-1">
                            <x-list-editor
                                name="featured_fields"
                                label="Featured Fields"
                                placeholder="Eg. KMCO"
                                :items="array_values(array_filter(array_map('trim', explode(',', old('featured_fields', ''))), 'strlen'))"
                            />
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="flex gap-4 w-full">
                        <div class="flex-1">
                            <h2 class="card-title">Position Preset</h2>

                            <select name="presetPositions" class="select select-bordered w-full">
                                <option disabled>Select preset</option>

                                <option value="" {{ old('presetPositions') == '' ? 'selected' : '' }}>
                                    No preset
                                </option>

                                @foreach ($presetPositions as $p)
                                    <option
                                        value="{{ $p }}"
                                        {{ old('presetPositions') == $p ? 'selected' : '' }}
                                    >
                                        {{ str_replace('_', ' ', $p) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex-1">
                            <h2 class="card-title">Event Banner</h2>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input file-input-bordered w-full rounded-full max-w-xs mb-2" />
                            <p class="text-sm opacity-70 mb-5">JPEG, PNG, GIF, or WebP, up to 8 MB.</p>
                        </div>
                    </div>

                    <div class="divider"></div>
                    <p>
                        By default, when an event is created, it is hidden from the calendar or list view.
                        This event will be archived, not deleted, 24 hours after the published end date. This can be reverted
                        through the event manager.
                        Create, publish, modify, and delete positions through the Event Manager, which you will be redirected to
                        after submission.
                        Editing this event later on will have no impact on the Event Manager, since it only deals with
                        positions.
                        You can un-hide this event from the events manager among other common tasks.
                        You will be notified of any errors in your submission after you submit the form. All changes will be
                        saved as long as you dont leave this page or refresh.
                    </p>

            </div>
        </div>

        <button class="btn btn-primary w-full mt-2" type="submit">
            Create Event
        </button>
    </form>



@endsection
