<div class="flex flex-col gap-4">
    @forelse($facilities as $facility)
        <div>
            <h3 class="font-semibold">{{ $facility->identifier }} &mdash; {{ $facility->name }}</h3>
            @if($facility->certificationLevels->isEmpty())
                <p class="text-base-content/60 text-sm">No levels defined.</p>
            @else
                <div class="flex flex-row flex-wrap gap-4 mt-1">
                    @foreach($facility->certificationLevels as $level)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm"
                                wire:click="toggleLevel({{ $level->id }})"
                                @checked(in_array($level->id, $heldLevelIds))>
                            <span>{{ $level->name }} <span class="badge badge-sm">{{ $level->abbreviation }}</span></span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <p class="text-base-content/60">No certification facilities defined.</p>
    @endforelse
</div>
