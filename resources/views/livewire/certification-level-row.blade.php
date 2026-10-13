<tr>
    @if($editing)
        <td>
            <input type="text" wire:model="name" class="input input-sm">
            @error('name') <span class="text-error text-xs block">{{ $message }}</span> @enderror
        </td>
        <td>
            <input type="text" maxlength="3" wire:model="abbreviation" class="input input-sm w-20">
            @error('abbreviation') <span class="text-error text-xs block">{{ $message }}</span> @enderror
        </td>
        <td>
            <input type="number" wire:model="level" class="input input-sm w-20">
            @error('level') <span class="text-error text-xs block">{{ $message }}</span> @enderror
        </td>
        <td class="flex gap-2">
            <button wire:key="save-{{ $certificationLevel->id }}" wire:click="save" class="btn btn-sm btn-primary">Save</button>
            <button wire:key="cancel-{{ $certificationLevel->id }}" wire:click="cancel" class="btn btn-sm btn-ghost">Cancel</button>
        </td>
    @else
        <td>{{ $certificationLevel->name }}</td>
        <td>{{ $certificationLevel->abbreviation }}</td>
        <td>{{ $certificationLevel->level }}</td>
        <td class="flex gap-2">
            <button wire:key="edit-{{ $certificationLevel->id }}" wire:click="edit" class="btn btn-sm btn-accent">Edit</button>
            <button
                wire:key="delete-{{ $certificationLevel->id }}"
                wire:click="delete"
                wire:confirm="Delete this level? This removes it from all users who hold it."
                class="btn btn-sm btn-error">Delete</button>
        </td>
    @endif
</tr>
