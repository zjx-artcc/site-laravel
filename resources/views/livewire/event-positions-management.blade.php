<div class="card bg-base-100 border border-base-300 w-full">
    <div class="card-body">
        <h2 class="card-title">Positions</h2>

        @unless ($readOnly)
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-48">
                    <label class="label">Load from a preset</label>
                    <select wire:model="selectedPreset" class="select select-bordered w-full">
                        <option value="">Choose a preset&hellip;</option>
                        @foreach ($presetNames as $preset)
                            <option value="{{ $preset }}">{{ $preset }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" wire:click="loadPreset" class="btn btn-secondary">
                    Load Preset
                </button>
            </div>

            <p class="text-xs opacity-70">
                Loading a preset replaces the list below and saves it immediately.
            </p>

            <div class="divider"></div>
        @endunless

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
            @forelse ($positions as $position)
                @php($isAssigned = in_array(strtoupper($position), $this->assignedPositions, true))
                <div class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 py-2 px-3 text-sm font-mono">
                    <span>{{ $position }}</span>
                    @unless ($readOnly)
                        @if ($isAssigned)
                            <x-locked-badge tip="A controller is already assigned to this position." />
                        @else
                            <button
                                type="button"
                                wire:click="removePosition('{{ $position }}')"
                                class="text-error"
                                aria-label="Remove {{ $position }}"
                            >&times;</button>
                        @endif
                    @endunless
                </div>
            @empty
                <span class="text-sm opacity-60 col-span-full">No positions added yet.</span>
            @endforelse
        </div>

        @unless ($readOnly)
            <div class="flex gap-2 mt-4">
                <input
                    type="text"
                    wire:model="newPosition"
                    wire:keydown.enter.prevent="addPosition"
                    placeholder="JAX_CTR"
                    class="input input-bordered flex-1"
                />
                <button type="button" wire:click="addPosition" class="btn btn-secondary">Add</button>
            </div>
        @endunless
    </div>
</div>
