@extends('layouts.main')

@section('title', 'Staffing Request')

@section('body')
    <div class="max-w-2xl">
        <x-card-component title="Request Staffing">
            <p class="text-lg mb-4">Need controllers staffed for an event? Submit the details below and our events
                team will follow up by email.</p>

            <form action="{{ route('staffing-requests.store') }}" method="POST" class="flex flex-col gap-y-4">
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

                <label class="form-control flex flex-col">
                    <span class="label-text font-semibold mb-1">Event Name</span>
                    <input type="text" name="name" class="input input-bordered w-full" placeholder="e.g. Jacksonville Fly-In"
                           value="{{ old('name') }}" required>
                    @error('name')
                        <span class="text-error text-sm mt-1">{{ $message }}</span>
                    @enderror
                </label>

                <label class="form-control flex flex-col">
                    <span class="label-text font-semibold mb-1">Date/Time Needed (Zulu)</span>
                    <input type="datetime-local" name="requested_at" class="input input-bordered w-full"
                           value="{{ old('requested_at') }}" required>
                    @error('requested_at')
                        <span class="text-error text-sm mt-1">{{ $message }}</span>
                    @enderror
                </label>

                <label class="form-control flex flex-col">
                    <span class="label-text font-semibold mb-1">Description</span>
                    <span class="text-sm opacity-70 mb-1">Tell us about your planned route, how many pilots are
                        flying, what kind of event it is, and any specific positions you'll need staffed.</span>
                    <textarea name="description" rows="6" class="textarea textarea-bordered w-full resize-none"
                              placeholder="Include airports, routes, pilot count, and any other staffing requirements needed."
                              required>{{ old('description') }}</textarea>
                    @error('description')
                        <span class="text-error text-sm mt-1">{{ $message }}</span>
                    @enderror
                </label>

                <button type="submit" class="btn btn-primary w-max">Submit Request</button>
            </form>
        </x-card-component>
    </div>
@endsection
