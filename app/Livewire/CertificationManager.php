<?php

namespace App\Livewire;

use App\Models\CertificationFacility;
use App\Models\User;
use App\Models\UserCertification;
use Livewire\WithPagination;

class CertificationManager extends SortableTable
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'last_name';

    public string $sortDirection = 'asc';

    // Which (user, facility) cell is being edited, if any.
    public ?int $editingUserId = null;

    public ?int $editingFacilityId = null;

    public function mount(): void
    {
        $this->authorizeWrite();
    }

    // Reset to the first page whenever the search term changes so results aren't
    // hidden on a now-out-of-range page.
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // Livewire action requests do not re-run the mounting route's permission
    // middleware, so every mutating action must re-check authorization itself.
    protected function authorizeWrite(): void
    {
        abort_unless(auth()->user()?->hasPermissionTo('certifications:write'), 403);
    }

    public function openEditor(int $userId, int $facilityId): void
    {
        $this->authorizeWrite();
        $this->editingUserId = $userId;
        $this->editingFacilityId = $facilityId;
    }

    public function closeEditor(): void
    {
        $this->reset(['editingUserId', 'editingFacilityId']);
    }

    public function toggleLevel(int $userId, int $levelId): void
    {
        $this->authorizeWrite();

        $existing = UserCertification::where('user_id', $userId)
            ->where('certification_level_id', $levelId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            UserCertification::create([
                'user_id' => $userId,
                'certification_level_id' => $levelId,
            ]);
        }
    }

    public function render()
    {
        $users = User::search($this->search)
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);

        // Eager-load so each cell's highest-level lookup doesn't hit the DB per facility.
        $users->load('certifications.certificationLevel');

        $facilities = CertificationFacility::orderBy('order')
            ->with('certificationLevels')
            ->get();

        $editingFacility = $this->editingFacilityId
            ? $facilities->firstWhere('id', $this->editingFacilityId)
            : null;
        $editingUser = $this->editingUserId
            ? User::find($this->editingUserId)
            : null;
        $editingLevelIds = $this->editingUserId
            ? UserCertification::where('user_id', $this->editingUserId)
                ->pluck('certification_level_id')->all()
            : [];

        return view('livewire.certification-manager', [
            'users' => $users,
            'facilities' => $facilities,
            'editingFacility' => $editingFacility,
            'editingUser' => $editingUser,
            'editingLevelIds' => $editingLevelIds,
        ]);
    }
}
