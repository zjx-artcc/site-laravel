@php($colors = \App\Livewire\SectorMap::COLORS)

<div class="flex gap-2 z-0">


<div class="w-full max-w-2xl mx-auto aspect-square flex flex-col">
    <div class="shrink-0">
        <p class="text-sm opacity-70 mb-3">Live ZJX center sector assignments.</p>

        {{-- Split selector --}}
        <div class="flex flex-row gap-x-6 mb-3">
            <label class="flex items-center gap-x-2 cursor-pointer">
                <input type="radio" name="split" class="radio radio-sm"
                       wire:click="setSplit('ultra')" @checked($split === 'ultra')>
                <span>Ultra High</span>
            </label>
            <label class="flex items-center gap-x-2 cursor-pointer">
                <input type="radio" name="split" class="radio radio-sm"
                       wire:click="setSplit('high')" @checked($split === 'high')>
                <span>High</span>
            </label>
            <label class="flex items-center gap-x-2 cursor-pointer">
                <input type="radio" name="split" class="radio radio-sm"
                       wire:click="setSplit('low')" @checked($split === 'low')>
                <span>Low</span>
            </label>
        </div>
    </div>

    {{-- Leaflet map, managed entirely by Alpine/Leaflet (kept out of Livewire morphing) --}}
    <div wire:ignore class="flex-1 min-h-0 z-0"
         x-data="sectorMap({
            highUrl: '{{ asset('geojson/high.json') }}',
            lowUrl: '{{ asset('geojson/low.json') }}',
            ultraUrl: '{{ asset('geojson/ultra.json') }}',
            colors: {{ \Illuminate\Support\Js::from($colors) }}
         })">
        <div x-ref="map" class="w-full h-full rounded-lg border border-base-300"></div>
    </div>

    {{-- Edit panel --}}
    <div class="flex flex-col gap-y-3 mt-3 shrink-0">
        @if($editMode)
            <p class="text-lg font-semibold">
                Currently selected:
                {{ $selectedSector ? 'ZJX ' . $selectedSector : 'OFFLINE' }}
            </p>
            <p class="text-sm opacity-70 -mt-2">Select a sector below, then click polygons on the map to assign them.</p>
        @endif

        {{-- Active sector palette --}}
        <div class="flex flex-row flex-wrap gap-2 items-center">
            @foreach($activeSectors as $i => $sector)
                <button type="button"
                        @disabled(!$editMode)
                        wire:click="selectSector({{ $sector }})"
                        class="btn btn-sm text-white border-2 {{ $selectedSector === $sector ? 'border-black' : 'border-transparent' }} {{ $editMode ? '' : 'cursor-default' }}"
                        style="background-color: {{ $colors[$i] ?? 'gray' }};">
                    ZJX {{ $sector }}
                </button>
            @endforeach

            <button type="button"
                    @disabled(!$editMode)
                    wire:click="selectSector(null)"
                    class="btn btn-sm text-white border-2 {{ $selectedSector === null && $editMode ? 'border-black' : 'border-transparent' }}"
                    style="background-color: gray;">
                OFFLINE
            </button>

            @if($editMode)
                <div class="join">
                    <input type="number" min="1" max="99" wire:model="newSector"
                           wire:keydown.enter.prevent="addNewSector"
                           placeholder="Sector #"
                           class="input input-sm input-bordered join-item w-28">
                    <button type="button" wire:click="addNewSector" class="btn btn-sm join-item">Add</button>
                </div>
            @endif
        </div>

        @error('sector')
            <div role="alert" class="alert alert-error alert-sm w-max max-w-full">
                <span>{{ $message }}</span>
            </div>
        @enderror

        {{-- Controls --}}
        @if($this->canEdit)
            <div class="flex flex-row flex-wrap gap-2 mt-1">
                @unless($editMode)
                    <button type="button" wire:click="enableEdit" class="btn btn-primary btn-sm">Edit</button>
                @else
                    <button type="button" wire:click="save" class="btn btn-success btn-sm">Save</button>
                    <button type="button" wire:click="cancelEdit" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="button" wire:click="resetAll" class="btn btn-sm">Reset all</button>
                    <button type="button" wire:click="clearAll" class="btn btn-sm">Clear all</button>
                @endunless
            </div>
        @endif
    </div>
</div>
</div>
