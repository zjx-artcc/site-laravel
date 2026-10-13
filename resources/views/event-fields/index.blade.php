@extends('layouts.admin')

@section('title', 'Event Field Presets')

@section('body')
    <div class="card bg-base-100 border border-base-300 w-full max-w-md mb-5">
        <div class="card-body">
            <h2 class="card-title">Add a Field</h2>

            <p class="text-sm opacity-70">
                Fields are the airports and facilities an event can be featured at, for example KJAX or KMCO.
            </p>

            <form method="POST" action="{{ route('admin.events.event-fields.store') }}" class="flex gap-3 mt-2">
                @csrf

                <input
                    type="text"
                    name="name"
                    required
                    maxlength="255"
                    placeholder="KJAX"
                    class="input input-bordered flex-1"
                    value="{{ old('name') }}"
                />

                <button type="submit" class="btn btn-primary">Add</button>
            </form>
        </div>
    </div>

    @if ($featuredFields->isEmpty())
        <h1>There are no event fields yet.</h1>
    @else
        <div class="overflow-x-auto">
        <table class="table table-zebra table-md w-max border-2 border-base-300">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Events</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($featuredFields as $field)
                    <tr>
                        <td class="py-3 border-r-1 border-base-300 font-medium">{{ $field->name }}</td>
                        <td class="py-3 border-r-1 border-base-300">
                            {{ \App\Models\Event::whereJsonContains('featured_fields', $field->name)->count() }}
                        </td>
                        <td class="py-3">
                            <form
                                action="{{ route('admin.events.event-fields.destroy', ['eventField' => $field->id]) }}"
                                method="POST"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="btn btn-error btn-sm"
                                    onclick="return confirm('Remove {{ $field->name }}?')"
                                >
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
@endsection
