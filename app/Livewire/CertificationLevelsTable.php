<?php

namespace App\Livewire;

use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CertificationLevelsTable extends Component
{
    public CertificationFacility $facility;

    // Add-level form
    public string $newName = '';

    public string $newAbbreviation = '';

    public ?int $newLevel = null;

    public function mount(int $facilityId): void
    {
        $this->authorizeWrite();

        $this->facility = CertificationFacility::with('certificationLevels')
            ->findOrFail($facilityId);
    }

    // Livewire action requests do not re-run the mounting route's permission
    // middleware, so every mutating action must re-check authorization itself.
    protected function authorizeWrite(): void
    {
        abort_unless(auth()->user()?->hasPermissionTo('certification-facilities:write'), 403);
    }

    public function createLevel(): void
    {
        $this->authorizeWrite();

        $facilityId = $this->facility->id;

        $validated = $this->validate([
            'newLevel' => [
                'required', 'integer', 'min:0',
                Rule::unique('certification_levels', 'level')
                    ->where(fn ($query) => $query->where('facility_id', $facilityId)),
            ],
            'newName' => [
                'required', 'string',
                Rule::unique('certification_levels', 'name')
                    ->where(fn ($query) => $query->where('facility_id', $facilityId)),
            ],
            'newAbbreviation' => [
                'required', 'string', 'max:3',
                Rule::unique('certification_levels', 'abbreviation')
                    ->where(fn ($query) => $query->where('facility_id', $facilityId)),
            ],
        ]);

        CertificationLevel::create([
            'facility_id' => $facilityId,
            'level' => $validated['newLevel'],
            'name' => $validated['newName'],
            'abbreviation' => $validated['newAbbreviation'],
        ]);

        $this->reset(['newName', 'newAbbreviation', 'newLevel']);
        $this->refreshLevels();
    }

    #[On('certification-level-saved')]
    public function handleSaved(): void
    {
        $this->refreshLevels();
    }

    #[On('certification-level-deleted')]
    public function handleDeleted(): void
    {
        $this->refreshLevels();
    }

    public function refreshLevels(): void
    {
        $this->facility->refresh();
    }

    public function render()
    {
        $this->facility->load([
            'certificationLevels' => fn ($query) => $query->orderBy('level', 'desc'),
        ]);

        return view('livewire.certification-levels-table', [
            'facility' => $this->facility,
        ]);
    }
}
