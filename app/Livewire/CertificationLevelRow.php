<?php

namespace App\Livewire;

use App\Models\CertificationLevel;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CertificationLevelRow extends Component
{
    public CertificationLevel $certificationLevel;

    public bool $editing = false;

    public string $name = '';

    public string $abbreviation = '';

    public ?int $level = null;

    public function mount(CertificationLevel $certificationLevel): void
    {
        $this->authorizeWrite();

        $this->certificationLevel = $certificationLevel;
    }

    // Livewire action requests do not re-run the mounting route's permission
    // middleware, so every mutating action must re-check authorization itself.
    protected function authorizeWrite(): void
    {
        abort_unless(auth()->user()?->hasPermissionTo('certification-facilities:write'), 403);
    }

    public function edit(): void
    {
        $this->editing = true;
        $this->name = $this->certificationLevel->name;
        $this->abbreviation = $this->certificationLevel->abbreviation;
        $this->level = $this->certificationLevel->level;
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $facilityId = $this->certificationLevel->facility_id;
        $id = $this->certificationLevel->id;

        $validated = $this->validate([
            'level' => [
                'required', 'integer', 'min:0',
                Rule::unique('certification_levels', 'level')
                    ->where(fn ($query) => $query->where('facility_id', $facilityId))->ignore($id),
            ],
            'name' => [
                'required', 'string',
                Rule::unique('certification_levels', 'name')
                    ->where(fn ($query) => $query->where('facility_id', $facilityId))->ignore($id),
            ],
            'abbreviation' => [
                'required', 'string', 'max:3',
                Rule::unique('certification_levels', 'abbreviation')
                    ->where(fn ($query) => $query->where('facility_id', $facilityId))->ignore($id),
            ],
        ]);

        $this->certificationLevel->update($validated);
        $this->editing = false;

        $this->dispatch('certification-level-saved');
    }

    public function delete(): void
    {
        $this->authorizeWrite();

        $this->certificationLevel->delete();

        $this->dispatch('certification-level-deleted');
    }

    public function render()
    {
        return view('livewire.certification-level-row');
    }
}
