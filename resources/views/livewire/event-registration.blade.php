<div class="card-body bg-neutral">
    <div class="flex items-center justify-between gap-2">
        <h2 class="card-title">Request Position</h2>
        @if ($submitted)
            <span class="badge badge-success badge-sm gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Submitted
            </span>
        @endif
    </div>

    @if (empty($positions))
        <p class="text-sm opacity-70 mt-2">No positions are available to request for this event yet.</p>
    @else
        <form
            @if (!$submitted) wire:submit.prevent="store" @else wire:submit.prevent="destroy" @endif
            class="flex flex-col gap-4 w-full"
        >
            @if ($errors->any())
                <div class="alert alert-error text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label for="selectedPosition" class="flex flex-col gap-1">
                    <span class="label-text font-semibold">Position</span>
                    <select
                        id="selectedPosition"
                        wire:model.live="selectedPosition"
                        class="select select-bordered w-full @error('selectedPosition') select-error @enderror"
                        @if ($submitted) disabled @endif
                    >
                        <option value="" disabled>Select a position</option>

                        @foreach ($positions as $p)
                            <option value="{{ $p }}">
                                {{ str_replace('_', ' ', $p) }}
                            </option>
                        @endforeach
                    </select>
                    @error('selectedPosition')
                        <span class="text-error text-xs">{{ $message }}</span>
                    @enderror
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label for="start" class="flex flex-col gap-1">
                        <span class="label-text font-semibold">Start (Z)</span>
                        @if (!$submitted)
                            <input
                                id="start"
                                type="datetime-local"
                                wire:model.live="start"
                                name="start"
                                min="{{ $event->start->utc()->format('Y-m-d\TH:i') }}"
                                max="{{ $event->end->utc()->format('Y-m-d\TH:i') }}"
                                class="input input-bordered w-full @error('start') input-error @enderror"
                            >
                            @error('start')
                                <span class="text-error text-xs">{{ $message }}</span>
                            @enderror
                        @else
                            <p class="text-sm py-2.5">{{ $start }}</p>
                        @endif
                    </label>

                    <label for="end" class="flex flex-col gap-1">
                        <span class="label-text font-semibold">End (Z)</span>
                        @if (!$submitted)
                            <input
                                id="end"
                                type="datetime-local"
                                wire:model.live="end"
                                name="end"
                                min="{{ $event->start->utc()->format('Y-m-d\TH:i') }}"
                                max="{{ $event->end->utc()->format('Y-m-d\TH:i') }}"
                                class="input input-bordered w-full @error('end') input-error @enderror"
                            >
                            @error('end')
                                <span class="text-error text-xs">{{ $message }}</span>
                            @enderror
                        @else
                            <p class="text-sm py-2.5">{{ $end }}</p>
                        @endif
                    </label>
                </div>
            </div>

            <label for="notes" class="flex flex-col gap-1">
                <div class="flex items-baseline justify-between">
                    <span class="label-text font-semibold">Additional Notes</span>
                    @if (!$submitted)
                        <span class="text-xs opacity-60">{{ strlen($notes ?? '') }}/500</span>
                    @endif
                </div>
                <textarea
                    id="notes"
                    wire:model.live="notes"
                    class="textarea textarea-bordered w-full @error('notes') textarea-error @enderror"
                    rows="3"
                    maxlength="500"
                    @if ($submitted) readonly @else placeholder="Eg. Operating on a solo cert" @endif
                >{{ $notes }}</textarea>
                @error('notes')
                    <span class="text-error text-xs">{{ $message }}</span>
                @enderror
            </label>

            <div class="flex justify-end">
                @if (!$submitted)
                    <button class="btn btn-primary w-full sm:w-auto" type="submit" wire:loading.attr="disabled" wire:target="store">
                        <span wire:loading.remove wire:target="store">Request Position</span>
                        <span wire:loading wire:target="store" class="loading loading-spinner loading-sm"></span>
                    </button>
                @else
                    <button
                        class="btn btn-error btn-outline w-full sm:w-auto"
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="destroy"
                        wire:confirm="Delete your position request? This cannot be undone."
                    >
                        <span wire:loading.remove wire:target="destroy">Delete Signup</span>
                        <span wire:loading wire:target="destroy" class="loading loading-spinner loading-sm"></span>
                    </button>
                @endif
            </div>
        </form>
    @endif
</div>
