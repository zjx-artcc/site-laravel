<div>
    {{-- Legend + stats --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-info badge-lg">{{ $counts->get('home', 0) }} home</span>
            <span class="badge badge-secondary badge-lg">{{ $counts->get('visitor', 0) }} visitor</span>
            <span class="badge badge-lg border-red-700 bg-red-600 text-white">{{ $counts->get('reserved', 0) }} reserved</span>
            <span class="badge badge-ghost badge-lg">{{ $counts->get('vacant', 0) }} vacant</span>
        </div>
        <div class="form-control">
            <label for="matrixFilter" class="label-text font-semibold mb-1">Highlight in matrix</label>
            <input
                id="matrixFilter"
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Type initials, a name, CID or reason…"
                class="input input-bordered input-sm w-72"
            >
            <span class="text-xs text-base-content/60 mt-1">Dims every OI that doesn't match. Doesn't assign anything.</span>
        </div>
    </div>

    @if($canManage)
        <div class="mb-4 rounded-box border border-base-300 bg-base-200/50 p-3">
            @if($assigningUser)
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p>
                        Choosing OI for <span class="font-semibold">{{ $assigningUser->name }}</span>
                        <span class="text-sm text-base-content/70">({{ $assigningUser->id }})</span>
                        — click a vacant OI below to assign it.
                    </p>
                    <div class="flex items-center gap-2">
                        @if($suggestion)
                            <button type="button" wire:click="select('{{ $suggestion }}')" wire:confirm="Assign {{ $suggestion }} to {{ $assigningUser->name }}?" class="btn btn-primary btn-sm">Use {{ $suggestion }}</button>
                        @endif
                        <button type="button" wire:click="clearController" class="btn btn-ghost btn-sm">Cancel</button>
                    </div>
                </div>
                @error('assignUserId') <p class="text-error text-sm mt-2">{{ $message }}</p> @enderror
            @else
                <label for="controllerSearch" class="label-text font-semibold">Find a controller to assign OI to</label>
                <input id="controllerSearch" type="text" wire:model.live.debounce.300ms="controllerSearch"
                    placeholder="Search name or CID (rostered, no OI yet)…" class="input input-bordered input-sm w-full max-w-md block mt-1" autocomplete="off">
                @if(trim($controllerSearch) !== '')
                    <ul class="menu bg-base-100 rounded-box mt-2 max-w-md shadow-sm">
                        @forelse($controllerResults as $result)
                            <li wire:key="ctrl-{{ $result->id }}">
                                <button type="button" wire:click="pickController({{ $result->id }})">
                                    {{ $result->name }} <span class="text-xs text-base-content/60">({{ $result->id }})</span>
                                </button>
                            </li>
                        @empty
                            <li class="px-3 py-2 text-sm text-base-content/60">No rostered controllers without an OI match.</li>
                        @endforelse
                    </ul>
                @endif
            @endif
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success mb-4"><span>{{ session('success') }}</span></div>
    @endif

    <div class="flex flex-col lg:flex-row gap-4">
        {{-- Matrix --}}
        <div class="grid grid-cols-6 sm:grid-cols-9 md:grid-cols-[repeat(13,minmax(0,1fr))] xl:grid-cols-[repeat(26,minmax(0,1fr))] gap-1 flex-1">
            @foreach($cells as $cell)
                <button
                    type="button"
                    wire:key="oi-{{ $cell['initials'] }}-{{ $assigningUser ? 'assign' : 'view' }}"
                    wire:click="select('{{ $cell['initials'] }}')"
                    @if($assigningUser && $cell['state'] === 'vacant')
                        wire:confirm="Assign {{ $cell['initials'] }} to {{ $assigningUser->name }}?"
                    @endif
                    @class([
                        'rounded border-2 py-1 text-sm font-mono text-center transition',
                        'border-info text-info' => $cell['state'] === 'home',
                        'border-secondary text-secondary' => $cell['state'] === 'visitor',
                        'border-red-700 bg-red-600 text-white font-bold' => $cell['state'] === 'reserved',
                        'border-base-300 hover:border-primary' => $cell['state'] === 'vacant',
                        'ring-2 ring-primary' => $selected === $cell['initials'],
                        'opacity-25' => ! $cell['matches'],
                    ])
                    title="{{ match($cell['state']) {
                        'home', 'visitor' => $cell['holder']->name.' ('.$cell['holder']->id.')',
                        'reserved' => 'Reserved'.($cell['reservation']->reason ? ': '.$cell['reservation']->reason : ''),
                        default => 'Vacant',
                    } }}"
                >{{ $cell['initials'] }}</button>
            @endforeach
        </div>

        {{-- Detail panel --}}
        @if($selectedCell)
            <div class="card bg-base-200 w-full lg:w-80 shrink-0 self-start" wire:key="oi-panel-{{ $selectedCell['initials'] }}">
                <div class="card-body gap-3">
                    <div class="flex items-center justify-between">
                        <h3 class="card-title font-mono text-2xl">{{ $selectedCell['initials'] }}</h3>
                        <button type="button" wire:click="closePanel" class="btn btn-ghost btn-sm btn-circle" aria-label="Close">✕</button>
                    </div>

                    @if(in_array($selectedCell['state'], ['home', 'visitor']))
                        <div>
                            <p class="font-semibold">
                                <a href="{{ route('users.edit', ['user' => $selectedCell['holder']->id]) }}" class="link" target="_blank">{{ $selectedCell['holder']->name }}</a>
                            </p>
                            <p class="text-sm text-base-content/70">
                                CID {{ $selectedCell['holder']->id }} · {{ $selectedCell['state'] === 'home' ? 'Home' : 'Visitor' }}
                                @unless($selectedCell['holder']->rostered) · <span class="text-warning">not rostered</span> @endunless
                            </p>
                        </div>
                        @if($canManage)
                            <button type="button" wire:click="unassign" wire:key="unassign-{{ $selectedCell['initials'] }}"
                                wire:confirm="Unassign {{ $selectedCell['initials'] }} from {{ $selectedCell['holder']->name }}?"
                                class="btn btn-warning btn-sm">Unassign</button>
                        @endif
                    @elseif($selectedCell['state'] === 'reserved')
                        <div>
                            <p class="badge border-red-700 bg-red-600 text-white">Reserved — cannot be assigned</p>
                            <p class="mt-2 text-sm">{{ $selectedCell['reservation']->reason ?: 'No reason recorded.' }}</p>
                            @if($selectedCell['reservation']->reservedBy)
                                <p class="text-xs text-base-content/60 mt-1">Reserved by {{ $selectedCell['reservation']->reservedBy->name }} on {{ $selectedCell['reservation']->created_at->format('Y-m-d') }}</p>
                            @endif
                        </div>
                        @if($canManage)
                            <button type="button" wire:click="release" wire:key="release-{{ $selectedCell['initials'] }}"
                                wire:confirm="Release {{ $selectedCell['initials'] }} so it can be assigned again?"
                                class="btn btn-warning btn-sm">Release reservation</button>
                        @endif
                    @else
                        <p class="badge badge-ghost">Vacant</p>
                        @if($canManage)
                            <div class="form-control">
                                <label class="label" for="assignUserId"><span class="label-text">Assign to a rostered controller</span></label>
                                @if($panelUser)
                                    <div class="flex items-center justify-between gap-2 rounded border border-base-300 bg-base-100 px-3 py-2 text-sm">
                                        <span class="font-semibold">{{ $panelUser->name }} <span class="font-normal text-base-content/60">({{ $panelUser->id }})</span></span>
                                        <button type="button" wire:click="clearPanelChoice" class="btn btn-ghost btn-xs">Change</button>
                                    </div>
                                @else
                                    <input id="assignUserId" type="text" wire:model.live.debounce.300ms="panelSearch"
                                        placeholder="Search name or CID…" class="input input-bordered input-sm w-full" autocomplete="off">
                                    @if(trim($panelSearch) !== '')
                                        <ul class="menu bg-base-100 rounded-box mt-1 w-full shadow-sm">
                                            @forelse($panelResults as $result)
                                                <li wire:key="panel-ctrl-{{ $result->id }}">
                                                    <button type="button" wire:click="chooseForPanel({{ $result->id }})">
                                                        {{ $result->name }} <span class="text-xs text-base-content/60">({{ $result->id }})</span>
                                                    </button>
                                                </li>
                                            @empty
                                                <li class="px-3 py-2 text-sm text-base-content/60">No rostered controllers without an OI match.</li>
                                            @endforelse
                                        </ul>
                                    @endif
                                @endif
                                @error('assignUserId') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                                <button type="button" wire:click="assign" class="btn btn-primary btn-sm mt-2">Assign</button>
                            </div>

                            <div class="divider my-0">or</div>

                            <div class="form-control">
                                <label class="label" for="reserveReason"><span class="label-text">Reserve (e.g. in memory of a member)</span></label>
                                <input id="reserveReason" type="text" wire:model="reserveReason" maxlength="255" placeholder="Reason / dedication" class="input input-bordered input-sm w-full">
                                @error('reserveReason') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                                <button type="button" wire:click="reserve" class="btn btn-sm mt-2 border-red-700 bg-red-600 text-white hover:bg-red-700">Reserve</button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
