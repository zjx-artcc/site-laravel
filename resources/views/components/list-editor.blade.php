@props([
    'items' => [],
    'label' => null,
    'placeholder' => 'Add an item...',
    'name' => null,
    'addAction' => null,
    'removeAction' => null,
    'itemModel' => null,
])

@php
    $isLivewire = $addAction !== null && $removeAction !== null;
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($label)
        <h2 class="card-title">{{ $label }}</h2>
    @endif

    @if ($isLivewire)
        {{-- Livewire mode: the server is truth, there is no local JS state to keep in sync. --}}
        <div class="flex flex-wrap gap-2 mb-2">
            @forelse ($items as $item)
                <span class="badge badge-outline gap-2">
                    {{ $item }}
                    <button
                        type="button"
                        wire:click="{{ $removeAction }}('{{ $item }}')"
                        class="text-error"
                        aria-label="Remove {{ $item }}"
                    >&times;</button>
                </span>
            @empty
                <span class="text-sm opacity-60">Nothing added yet.</span>
            @endforelse
        </div>

        <div class="flex gap-2">
            <input
                type="text"
                wire:model="{{ $itemModel }}"
                wire:keydown.enter.prevent="{{ $addAction }}"
                placeholder="{{ $placeholder }}"
                class="input input-bordered flex-1"
            />
            <button type="button" wire:click="{{ $addAction }}" class="btn btn-secondary">Add</button>
        </div>
    @else
        {{-- Plain-form mode: Alpine-only, posts as one comma-joined hidden input so the
             server-side parsing this replaces (parseFeaturedFields/parsePositions) is unchanged. --}}
        <div x-data="{ items: @js(array_values($items)), draft: '' }">
            <input type="hidden" name="{{ $name }}" :value="items.join(',')" />

            <div class="flex flex-wrap gap-2 mb-2">
                <template x-for="(item, index) in items" :key="index">
                    <span class="badge badge-outline gap-2">
                        <span x-text="item"></span>
                        <button type="button" @click="items.splice(index, 1)" class="text-error" aria-label="Remove item">&times;</button>
                    </span>
                </template>
                <span class="text-sm opacity-60" x-show="items.length === 0">Nothing added yet.</span>
            </div>

            <div class="flex gap-2">
                <input
                    type="text"
                    x-model="draft"
                    @keydown.enter.prevent="if (draft.trim() && !items.some(i => i.toLowerCase() === draft.trim().toLowerCase())) { items.push(draft.trim()); draft = ''; }"
                    placeholder="{{ $placeholder }}"
                    class="input input-bordered flex-1"
                />
                <button
                    type="button"
                    @click="if (draft.trim() && !items.some(i => i.toLowerCase() === draft.trim().toLowerCase())) { items.push(draft.trim()); draft = ''; }"
                    class="btn btn-secondary"
                >Add</button>
            </div>
        </div>
    @endif
</div>
