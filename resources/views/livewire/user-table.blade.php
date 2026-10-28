<div>
    {{-- Summary --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-neutral badge-lg">{{ $stats['rostered'] }} rostered</span>
            <span class="badge badge-info badge-lg">{{ $stats['home'] }} home</span>
            <span class="badge badge-secondary badge-lg">{{ $stats['visitors'] }} visiting</span>
            <button type="button" wire:click="filterOi('unassigned')"
                @class(['badge badge-lg cursor-pointer', 'badge-warning' => $stats['missingOi'] > 0, 'badge-ghost' => $stats['missingOi'] === 0])
                title="Show rostered controllers without operating initials">{{ $stats['missingOi'] }} without OI</button>
        </div>
        @if($canManage)
            <a href="{{ route('admin.oi-matrix') }}" class="btn btn-sm btn-primary">Open OI Matrix</a>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <div class="form-control">
            <label for="userSearch" class="label-text font-semibold mb-1">Search</label>
            <input id="userSearch" type="text" wire:model.live.debounce.300ms="search"
                placeholder="Name, CID, OI{{ $canManage ? ', email' : '' }}…" class="input input-bordered input-sm w-64" autocomplete="off">
        </div>
        <div class="form-control">
            <label for="userStatus" class="label-text font-semibold mb-1">Roster</label>
            <select id="userStatus" wire:model.live="status" class="select select-bordered select-sm">
                <option value="rostered">Rostered</option>
                <option value="not-rostered">Not rostered</option>
                <option value="all">Everyone</option>
            </select>
        </div>
        <div class="form-control">
            <label for="userMembership" class="label-text font-semibold mb-1">Home / visitor</label>
            <select id="userMembership" wire:model.live="membership" class="select select-bordered select-sm">
                <option value="all">All</option>
                <option value="home">Home</option>
                <option value="visitor">Visitor</option>
            </select>
        </div>
        <div class="form-control">
            <label for="userOi" class="label-text font-semibold mb-1">OI</label>
            <select id="userOi" wire:model.live="oi" class="select select-bordered select-sm">
                <option value="all">All</option>
                <option value="assigned">Assigned</option>
                <option value="unassigned">Unassigned</option>
            </select>
        </div>
        <div class="form-control">
            <label for="userRating" class="label-text font-semibold mb-1">Rating</label>
            <select id="userRating" wire:model.live="rating" class="select select-bordered select-sm">
                <option value="">Any</option>
                @foreach($ratings as $r)
                    <option value="{{ $r->value }}">{{ $r->mapToString() }}</option>
                @endforeach
            </select>
        </div>
        @if($filtersActive)
            <button type="button" wire:click="resetFilters" class="btn btn-ghost btn-sm">Reset filters</button>
        @endif
    </div>

    @php
        $sortIcon = fn (string $field) => $sortField === $field ? ($sortDirection === 'asc' ? '▲' : '▼') : '';
    @endphp

    @if($users->count() > 0)
        <div class="overflow-x-auto">
            <table class="table table-zebra table-md w-full border-2 rounded-md border-base-300">
                <thead>
                    <tr>
                        <th><button type="button" wire:click="sortBy('last_name')" class="font-bold">Name {{ $sortIcon('last_name') }}</button></th>
                        <th><button type="button" wire:click="sortBy('id')" class="font-bold">CID {{ $sortIcon('id') }}</button></th>
                        <th><button type="button" wire:click="sortBy('operating_initials')" class="font-bold">OI {{ $sortIcon('operating_initials') }}</button></th>
                        <th><button type="button" wire:click="sortBy('rating')" class="font-bold">Rating {{ $sortIcon('rating') }}</button></th>
                        @if($canManage)
                            <th>Email</th>
                            <th><button type="button" wire:click="sortBy('joined_at')" class="font-bold">Joined {{ $sortIcon('joined_at') }}</button></th>
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td>
                                <a href="{{ route('users.show', ['user' => $user->id]) }}" class="text-base-content no-underline">{{ $user->nameReversed }}</a>
                                @unless(strcasecmp((string) $user->facility, (string) config('app.vatusa_facility')) === 0)
                                    <span class="badge badge-info badge-sm ml-2">{{ $user->facility ?: 'No facility' }} Visitor</span>
                                @endunless
                                @unless($user->rostered)
                                    <span class="badge badge-ghost badge-sm ml-2">Not rostered</span>
                                @endunless
                            </td>
                            <td>{{ $user->id }}</td>
                            <td>
                                @if($user->operating_initials)
                                    <span class="badge badge-outline font-mono">{{ $user->operating_initials }}</span>
                                @elseif($user->rostered && $canManage)
                                    <a href="{{ route('admin.oi-matrix', ['controller' => $user->id]) }}" class="btn btn-warning btn-xs">Assign OI</a>
                                @elseif($user->rostered)
                                    <span class="text-warning">Unassigned</span>
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                            <td>{{ $user->rating->mapToString() }}</td>
                            @if($canManage)
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->joined_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('users.edit', ['user' => $user->id]) }}" class="btn btn-sm">Edit</a>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="py-6 text-center text-base-content/70">No users match these filters.</p>
    @endif

    <div class="w-full max-w-150 mt-5">
        {{ $users->links() }}
    </div>
</div>
