<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventPosition;
use App\Models\EventPositionPreset;
use Livewire\Component;

class EventPositionsManagement extends Component
{
    public Event $event;

    public array $positions = [];

    public string $newPosition = '';

    public array $presetNames = [];

    public string $selectedPreset = '';

    public bool $readOnly = false;

    public function mount(Event $event, bool $readOnly = false)
    {
        $this->event = $event;
        $this->readOnly = $readOnly;
        $this->positions = $event->presetPositions ?? [];
        $this->presetNames = EventPositionPreset::orderBy('name')->pluck('name')->all();
    }

    /**
     * Positions with at least one registrant assigned — removing these is blocked.
     *
     * @return array<int, string>
     */
    public function getAssignedPositionsProperty(): array
    {
        return EventPosition::assignedPositionsOutsideOf($this->event->id, [])->all();
    }

    public function addPosition()
    {
        abort_unless(auth()->user()?->can('assign event positions'), 403);

        $position = strtoupper(trim($this->newPosition));
        $this->newPosition = '';

        if ($position === '' || $this->hasPosition($position)) {
            return;
        }

        $this->positions[] = $position;

        // Adding can never remove an assigned position, so it's safe to persist
        // immediately instead of waiting on a separate Save click.
        $this->save();
    }

    public function removePosition(string $position)
    {
        abort_unless(auth()->user()?->can('assign event positions'), 403);

        if (in_array(strtoupper($position), $this->assignedPositions, true)) {
            $this->dispatch('notify', type: 'error', message: "Can't remove {$position} — a controller is already assigned to it.");

            return;
        }

        $this->positions = collect($this->positions)
            ->reject(fn ($p) => strtoupper($p) === strtoupper($position))
            ->values()
            ->all();

        $this->save();
    }

    public function loadPreset()
    {
        abort_unless(auth()->user()?->can('assign event positions'), 403);

        if ($this->selectedPreset === '') {
            return;
        }

        $preset = EventPositionPreset::where('name', $this->selectedPreset)->first();

        abort_if($preset === null, 404);

        $this->positions = collect($preset->positions ?? [])
            ->map(fn ($p) => strtoupper(trim($p)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->selectedPreset = '';

        $this->save();
    }

    public function save()
    {
        // Livewire's update endpoint does not inherit the mounting page's route
        // middleware, so mutators have to re-check the permission themselves.
        abort_unless(auth()->user()?->hasPermissionTo('assign event positions'), 403);

        $newPositions = collect($this->positions)
            ->map(fn ($position) => strtoupper(trim($position)))
            ->filter()
            ->unique()
            ->values();

        $blockedPositions = EventPosition::assignedPositionsOutsideOf($this->event->id, $newPositions->all());

        if ($blockedPositions->isNotEmpty()) {
            $this->positions = $this->event->fresh()->presetPositions ?? [];

            $this->dispatch('notify', type: 'error', message: 'Cannot remove assigned position(s): '.$blockedPositions->implode(', '));

            return;
        }

        $positions = $newPositions->all();

        $this->event->update(['presetPositions' => $positions]);

        $this->positions = $positions;

        $this->dispatch('notify', type: 'success', message: 'Positions updated.');
    }

    private function hasPosition(string $position): bool
    {
        return collect($this->positions)->contains(fn ($p) => strtoupper($p) === strtoupper($position));
    }
}
