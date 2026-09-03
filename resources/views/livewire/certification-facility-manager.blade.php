<div>
    <div class="overflow-x-auto">
    <table class="table table-zebra">
        <thead>
            <tr>
                <th>Name</th>
                <th>Identifier</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($facilities as $facility)
                <tr>
                    @if($editingId === $facility->id)
                        <td>
                            <input type="text" wire:model="editName" class="input input-sm">
                            @error('editName') <span class="text-error text-xs block">{{ $message }}</span> @enderror
                        </td>
                        <td>
                            <input type="text" maxlength="10" wire:model="editIdentifier" class="input input-sm">
                            @error('editIdentifier') <span class="text-error text-xs block">{{ $message }}</span> @enderror
                        </td>
                        <td>
                            <input type="number" wire:model="editOrder" class="input input-sm w-20">
                            @error('editOrder') <span class="text-error text-xs block">{{ $message }}</span> @enderror
                        </td>
                        <td class="flex gap-2">
                            <button wire:key="save-{{ $facility->id }}" wire:click="updateFacility" class="btn btn-sm btn-primary">Save</button>
                            <button wire:key="cancel-{{ $facility->id }}" wire:click="cancelEdit" class="btn btn-sm btn-ghost">Cancel</button>
                        </td>
                    @else
                        <td>
                            <a class="link link-primary" href="{{ route('certification-facilities.show', $facility->id) }}">{{ $facility->name }}</a>
                        </td>
                        <td>{{ $facility->identifier }}</td>
                        <td>{{ $facility->order }}</td>
                        <td class="flex gap-2">
                            <button wire:key="edit-{{ $facility->id }}" wire:click="startEdit({{ $facility->id }})" class="btn btn-sm btn-accent">Edit</button>
                            <button
                                wire:key="delete-{{ $facility->id }}"
                                wire:click="deleteFacility({{ $facility->id }})"
                                wire:confirm="Are you sure you want to delete this facility? This will delete all levels and related certifications for all users, and is irreversible."
                                class="btn btn-sm btn-error">Delete</button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="4">No certification facilities defined.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <form wire:submit="createFacility" class="mt-10 border-t-1 border-base-300 pt-5 flex flex-col gap-2 w-max">
        <h2 class="text-xl">Add Facility</h2>
        <div class="flex flex-col gap-1">
            <label class="label" for="name">Facility Name</label>
            <input type="text" class="input" id="name" wire:model="name">
            @error('name') <span class="text-error text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label class="label" for="identifier">Identifier</label>
            <input type="text" class="input" id="identifier" maxlength="10" wire:model="identifier">
            @error('identifier') <span class="text-error text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label class="label" for="order">Order</label>
            <input type="number" class="input" id="order" wire:model="order">
            @error('order') <span class="text-error text-xs">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary mt-2">Add Facility</button>
    </form>
</div>
