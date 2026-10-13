<?php

namespace App\Livewire;

use App\Models\CertificationFacility;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CertificationFacilityManager extends Component
{
    // Create form
    public string $name = '';

    public string $identifier = '';

    public int $order = 0;

    // Inline edit state
    public ?int $editingId = null;

    public string $editName = '';

    public string $editIdentifier = '';

    public int $editOrder = 0;

    public function mount(): void
    {
        $this->authorizeWrite();
    }

    // Livewire action requests do not re-run the mounting route's permission
    // middleware, so every mutating action must re-check authorization itself.
    protected function authorizeWrite(): void
    {
        abort_unless(auth()->user()?->hasPermissionTo('certification-facilities:write'), 403);
    }

    public function createFacility(): void
    {
        $this->authorizeWrite();

        // Identifier is uppercased on read by the model; normalize before the
        // uniqueness check so case-variants ('zjx' vs 'ZJX') can't both be stored.
        $this->identifier = strtoupper($this->identifier);

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:10|unique:certification_facilities,identifier',
            'order' => 'required|integer',
        ]);

        CertificationFacility::create($validated);

        $this->reset(['name', 'identifier', 'order']);
    }

    public function startEdit(int $id): void
    {
        $this->authorizeWrite();

        $facility = CertificationFacility::findOrFail($id);
        $this->editingId = $facility->id;
        $this->editName = $facility->name;
        $this->editIdentifier = $facility->identifier;
        $this->editOrder = $facility->order;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editName', 'editIdentifier', 'editOrder']);
        $this->resetErrorBag();
    }

    public function updateFacility(): void
    {
        $this->authorizeWrite();

        $this->editIdentifier = strtoupper($this->editIdentifier);

        $validated = $this->validate([
            'editName' => 'required|string|max:255',
            'editIdentifier' => [
                'required', 'string', 'max:10',
                Rule::unique('certification_facilities', 'identifier')->ignore($this->editingId),
            ],
            'editOrder' => 'required|integer',
        ]);

        CertificationFacility::findOrFail($this->editingId)->update([
            'name' => $validated['editName'],
            'identifier' => $validated['editIdentifier'],
            'order' => $validated['editOrder'],
        ]);

        $this->cancelEdit();
    }

    public function deleteFacility(int $id): void
    {
        $this->authorizeWrite();

        CertificationFacility::destroy($id);
    }

    public function render()
    {
        return view('livewire.certification-facility-manager', [
            'facilities' => CertificationFacility::orderBy('order')->orderBy('name')->get(),
        ]);
    }
}
