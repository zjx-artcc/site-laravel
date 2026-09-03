<div>
    <div class='overflow-x-auto'>
    <table class='table table-compact w-full mt-5'>
        <thead>
            <tr>
                <th>Name</th>
                <th>Abbreviation</th>
                <th>Level</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($facility->certificationLevels as $level)
                @livewire('certification-level-row', ['certificationLevel' => $level], key($level->id))
            @empty
                <tr>
                    <td colspan='4'>No certification levels defined.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <form wire:submit="createLevel" class='flex flex-col gap-2 w-max mt-4'>
        <h2 class='text-xl'>Add Certification Level</h2>
        <div>
            <label for="newName" class='label'>Name</label>
            <br>
            <input type="text" wire:model="newName" id="newName" class="input input-sm">
            @error('newName') <span class="text-error text-xs block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="newAbbreviation" class='label'>Abbreviation</label>
            <br>
            <input type="text" wire:model="newAbbreviation" id="newAbbreviation" maxlength="3" class="input input-sm">
            @error('newAbbreviation') <span class="text-error text-xs block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="newLevel" class='label'>Level</label>
            <br>
            <input type="number" wire:model="newLevel" id="newLevel" class="input input-sm">
            @error('newLevel') <span class="text-error text-xs block">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Add Level</button>
    </form>
</div>
