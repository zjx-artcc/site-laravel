<?php

namespace App\Livewire;

use App\Enums\ControllerRating;
use App\Models\CenterSector;
use App\Models\OnlineController;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SectorMap extends Component
{
    /**
     * Color palette used to distinguish active sectors. The index of an
     * active sector id within $activeSectors maps to a color here. A sector
     * with no assignment renders gray, so the palette length also caps how
     * many distinct sectors can be active at once.
     */
    public const COLORS = [
        'red',
        'green',
        'blue',
        'orange',
        'purple',
        'aquamarine',
        'darkslategray',
        'deeppink',
        'sienna',
    ];

    /** Which split is being viewed: 'high' or 'low'. */
    public string $split = 'high';

    /** Whether the editor controls are active. */
    public bool $editMode = false;

    /** The active sector currently selected as the "paint" target (null = OFFLINE). */
    public ?int $selectedSector = null;

    /** Map of sector_id => active_sector_id for sectors that are currently online. */
    public array $sectors = [];

    /** Unsaved edits for other splits visited during this edit session, keyed by split. */
    public array $pendingSectors = [];

    /** Ordered list of unique active sector ids; index drives the color. */
    public array $activeSectors = [];

    /** Bound to the "add sector" input in edit mode. */
    public string $newSector = '';

    public function mount(): void
    {
        $this->loadFromDatabase();
    }

    /**
     * Keep the public Livewire state scalar before Blade renders it.
     *
     * Livewire properties are hydrated from the browser on every update. A
     * malformed palette entry would otherwise be passed directly to Blade's
     * escaped output and cause `htmlspecialchars()` to throw a TypeError.
     */
    public function hydrate(): void
    {
        $this->activeSectors = $this->normalizeSectorIds($this->activeSectors);
    }

    public function updatedActiveSectors(): void
    {
        $this->activeSectors = $this->normalizeSectorIds($this->activeSectors);
    }

    public function render()
    {
        return view('livewire.sector-map', [
            'onlineSessions' => OnlineController::all(),
        ]);
    }

    #[Computed]
    public function canEdit(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // C1 and above controllers, or any facility staff member.
        return $user->rating->value >= ControllerRating::C1->value
            || $user->staffRoles()->exists();
    }

    public function setSplit(string $split): void
    {
        if (! in_array($split, ['high', 'low', 'ultra'], true)) {
            return;
        }

        if ($this->editMode) {
            // Stash unsaved edits for the split we're leaving and restore any
            // we already made on the one we're entering.
            $this->pendingSectors[$this->split] = $this->sectors;
            $this->split = $split;
            $this->sectors = $this->pendingSectors[$split] ?? $this->splitSectorsFromDatabase($split);
        } else {
            $this->split = $split;
            $this->loadFromDatabase();
        }

        $this->dispatch('split-changed');
    }

    public function enableEdit(): void
    {
        if ($this->canEdit()) {
            $this->editMode = true;
            $this->pendingSectors = [];
        }
    }

    /** Select the active sector to paint with (null clears to OFFLINE). */
    public function selectSector(?int $sectorId): void
    {
        if (! $this->editMode) {
            return;
        }

        $this->selectedSector = $sectorId;
    }

    /** Assign the clicked map polygon to the currently selected active sector. */
    public function assignSector(int $sectorId): void
    {
        if (! $this->editMode || ! $this->canEdit() || $sectorId >= 100) {
            return;
        }

        if ($this->selectedSector === null) {
            unset($this->sectors[$sectorId]);
        } else {
            $this->sectors[$sectorId] = $this->selectedSector;
        }

        $this->reparseActiveSectors();
        $this->dispatch('sectors-updated');
    }

    /** Handle the "add sector" input from the edit panel. */
    public function addNewSector(): void
    {
        if (! is_numeric(trim($this->newSector))) {
            $this->addError('sector', 'Entered value is not a number.');

            return;
        }

        $this->addSector((int) $this->newSector);

        if (! $this->getErrorBag()->has('sector')) {
            $this->newSector = '';
        }
    }

    /** Add a new active sector to the palette and select it. */
    public function addSector(int $sectorId): void
    {
        if (! $this->editMode || ! $this->canEdit()) {
            return;
        }

        if ($sectorId <= 0 || $sectorId >= 100) {
            $this->addError('sector', 'Sector does not exist.');

            return;
        }

        if (in_array($sectorId, $this->activeSectors, true)) {
            $this->addError('sector', "Sector {$sectorId} is already active.");

            return;
        }

        if (count($this->activeSectors) >= count(self::COLORS)) {
            $this->addError('sector', 'Cannot add more than '.count(self::COLORS).' active sectors.');

            return;
        }

        $this->activeSectors[] = $sectorId;
        $this->selectedSector = $sectorId;
        $this->resetErrorBag('sector');
        $this->dispatch('sectors-updated');
    }

    /** Set every sector OFFLINE while keeping the palette intact. */
    public function clearAll(): void
    {
        if (! $this->editMode || ! $this->canEdit()) {
            return;
        }

        $this->sectors = [];
        $this->reparseActiveSectors();
        $this->dispatch('sectors-updated');
    }

    /** Discard local changes and reload the saved split. */
    public function resetAll(): void
    {
        $this->loadFromDatabase();
        $this->dispatch('sectors-updated');
    }

    public function save(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        // Persist every split touched during this edit session.
        $this->pendingSectors[$this->split] = $this->sectors;

        foreach ($this->pendingSectors as $split => $sectors) {
            CenterSector::where('split', $split)->delete();

            foreach ($sectors as $sectorId => $activeSectorId) {
                if ($sectorId < 100 && $activeSectorId !== null) {
                    CenterSector::create([
                        'split' => $split,
                        'sector_id' => $sectorId,
                        'active_sector_id' => $activeSectorId,
                    ]);
                }
            }
        }

        $this->pendingSectors = [];
        $this->editMode = false;
        $this->selectedSector = null;
        $this->loadFromDatabase();
        $this->dispatch('sectors-updated');
        $this->dispatch('split-saved');
    }

    public function cancelEdit(): void
    {
        $this->editMode = false;
        $this->selectedSector = null;
        $this->pendingSectors = [];
        $this->loadFromDatabase();
        $this->dispatch('sectors-updated');
    }

    /** Saved sector assignments for one split. */
    private function splitSectorsFromDatabase(string $split): array
    {
        $sectors = [];

        foreach (CenterSector::where('split', $split)->orderBy('sector_id')->get() as $record) {
            if ($record->active_sector_id !== null) {
                $sectors[$record->sector_id] = $record->active_sector_id;
            }
        }

        return $sectors;
    }

    private function loadFromDatabase(): void
    {
        $sectors = [];

        // Polygon assignments are per split, but the active sector palette is
        // shared across all splits so it (and its colors) survives switching
        // maps. Existing palette order is kept so colors stay stable; entries
        // added this session but not painted anywhere also survive reloads.
        $activeSectors = $this->normalizeSectorIds($this->activeSectors);

        foreach (CenterSector::orderBy('id')->get() as $record) {
            if ($record->active_sector_id === null) {
                continue;
            }

            if ($record->split === $this->split) {
                $sectors[$record->sector_id] = $record->active_sector_id;
            }

            if (! in_array($record->active_sector_id, $activeSectors, true)) {
                $activeSectors[] = $record->active_sector_id;
            }
        }

        $this->sectors = $sectors;
        $this->activeSectors = $activeSectors;
    }

    /** Append any newly painted sectors to the palette, keeping its order stable. */
    private function reparseActiveSectors(): void
    {
        $activeSectors = $this->normalizeSectorIds($this->activeSectors);

        foreach ($this->sectors as $activeSectorId) {
            if ($activeSectorId !== null && ! in_array($activeSectorId, $activeSectors, true)) {
                $activeSectors[] = $activeSectorId;
            }
        }

        $this->activeSectors = $activeSectors;
    }

    /** @return list<int> */
    private function normalizeSectorIds(array $sectorIds): array
    {
        $normalized = [];

        foreach ($sectorIds as $sectorId) {
            $sectorId = $this->normalizeSectorId($sectorId);

            if ($sectorId !== null && ! in_array($sectorId, $normalized, true)) {
                $normalized[] = $sectorId;
            }
        }

        return $normalized;
    }

    private function normalizeSectorId(mixed $sectorId): ?int
    {
        if (is_string($sectorId) && ctype_digit($sectorId)) {
            $sectorId = (int) $sectorId;
        }

        if (! is_int($sectorId) || $sectorId <= 0 || $sectorId >= 100) {
            return null;
        }

        return $sectorId;
    }
}
