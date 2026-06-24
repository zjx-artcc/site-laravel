<div class="card-body bg-neutral p-4 sm:p-6">
    <h2 class="card-title text-base sm:text-lg">Request Position</h2>
    <form @if (!$submitted) wire:submit.prevent="store" @else wire:submit.prevent="destroy" @endif
        class="flex flex-col gap-4 w-full">

        {{-- Position select --}}
        <div class="flex flex-col w-full">
            <select wire:model="selectedPosition" required class="select select-bordered w-full"
                @if ($submitted) disabled @endif>
                <option value="" disabled>Select a position</option>
                @foreach ($positions as $p)
                    <option value="{{ $p }}">{{ str_replace('_', ' ', $p) }}</option>
                @endforeach
            </select>
            <p class="text-xs text-base-content/50 mt-1">Pick from selections</p>
        </div>

        {{-- Start / End — stack on mobile, side-by-side on sm+ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="flex flex-col w-full">
                <label for="start" class="label-text text-sm">Requested Start</label>
                @if (!$submitted)
                    <input type="datetime-local" wire:model="start" name="start"
                        class="input input-bordered w-full" required>
                @else
                    <p class="text-sm py-2">{{ $event->start->format('M j, Y g:i A') }}</p>
                @endif
            </div>

            <div class="flex flex-col w-full">
                <label for="end" class="label-text text-sm">Requested End</label>
                @if (!$submitted)
                    <input type="datetime-local" wire:model="end" name="end"
                        class="input input-bordered w-full" required>
                @else
                    <p class="text-sm py-2">{{ $event->end->format('M j, Y g:i A') }}</p>
                @endif
            </div>
        </div>

        {{-- Notes --}}
        <div class="flex flex-col w-full">
            <label for="notes" class="label-text text-sm">Additional Notes</label>
            <textarea wire:model="notes"
                @if ($submitted) placeholder="{{ old('notes', $event->notes) }}" @else placeholder="Eg. Operating on a solo cert" @endif
                class="textarea textarea-bordered w-full" rows="4" @if ($submitted) readonly @endif></textarea>
        </div>

        @if (!$submitted)
            <button class="btn btn-primary w-full sm:w-auto" type="submit">Request Position</button>
        @else
            <button class="btn btn-error w-full sm:w-auto" type="submit">Delete Signup</button>
        @endif
    </form>
</div>
