<?php

namespace App\Livewire;

use App\Models\CertificationFacility;
use App\Models\User;
use App\Models\UserCertification;
use Livewire\Component;

class UserCertifications extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function toggleLevel(int $levelId): void
    {
        abort_unless(auth()->user()?->hasPermissionTo('certifications:write'), 403);

        $existing = UserCertification::where('user_id', $this->user->id)
            ->where('certification_level_id', $levelId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            UserCertification::create([
                'user_id' => $this->user->id,
                'certification_level_id' => $levelId,
            ]);
        }
    }

    public function render()
    {
        $facilities = CertificationFacility::orderBy('order')
            ->with('certificationLevels')
            ->get();

        $heldLevelIds = UserCertification::where('user_id', $this->user->id)
            ->pluck('certification_level_id')
            ->all();

        return view('livewire.user-certifications', [
            'facilities' => $facilities,
            'heldLevelIds' => $heldLevelIds,
        ]);
    }
}
