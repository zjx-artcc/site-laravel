<div>
    <div class="flex flex-col mb-3">
        <label for="search" class="label">Search</label>
        <input
            type="text"
            id="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Name or CID"
            class="input input-sm max-w-xs">
    </div>

    @unless(sizeof($users) == 0)
        <div class="overflow-x-auto">
        <table class='table table-zebra table-md w-max border-2 rounded-md border-base-300 mt-5'>
            <thead>
                <tr class='text-xl font-bold'>
                    <th colspan='2'>Certification Management</th>
                    <th colspan='{{ count($facilities) }}'>Certifications</th>
                </tr>
                <tr>
                    <th>Name (CID)</th>
                    <th>Rating</th>
                    @foreach($facilities as $facility)
                        <th>{{ $facility->identifier }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class='border-r-1 border-base-300'>
                            <a href="{{ route('users.show', ['user' => $user->id]) }}" class='text-base-content no-underline'>
                                {{ $user->nameReversed }} ({{ $user->id }})
                            </a>
                        </td>
                        <td class='border-r-1 border-base-300'>{{ $user->rating->mapToString() }}</td>

                        @foreach($facilities as $facility)
                            @php $level = $user->highestCertificationLevelFor($facility->id); @endphp
                            <td
                                class='text-center cursor-pointer hover:bg-base-300 @if($level) bg-success text-success-content font-semibold @else text-base-content/60 @endif'
                                wire:click="openEditor({{ $user->id }}, {{ $facility->id }})">
                                {{ $level?->abbreviation ?? 'Uncertified' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @else
        <h1>There are no rostered users.</h1>
    @endunless

    <div class="w-full max-w-150 mt-5">
        {{ $users->links() }}
    </div>

    @if($editingUser && $editingFacility)
        <div class="modal modal-open">
            <div class="modal-box">
                <h3 class="text-lg font-bold">
                    {{ $editingUser->nameReversed }} &mdash; {{ $editingFacility->identifier }} certifications
                </h3>
                <p class="text-sm text-base-content/60 mb-3">Check every level this controller holds. The roster shows the highest.</p>

                <div class="flex flex-col gap-2">
                    @forelse($editingFacility->certificationLevels as $level)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                class="checkbox"
                                wire:click="toggleLevel({{ $editingUser->id }}, {{ $level->id }})"
                                @checked(in_array($level->id, $editingLevelIds))>
                            <span>{{ $level->name }} <span class="badge badge-sm">{{ $level->abbreviation }}</span> (level {{ $level->level }})</span>
                        </label>
                    @empty
                        <p class="text-base-content/60">This facility has no certification levels defined.</p>
                    @endforelse
                </div>

                <div class="modal-action">
                    <button wire:click="closeEditor" class="btn btn-primary">Done</button>
                </div>
            </div>
            <div class="modal-backdrop" wire:click="closeEditor"></div>
        </div>
    @endif
</div>
