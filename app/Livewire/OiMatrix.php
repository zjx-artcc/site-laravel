<?php

namespace App\Livewire;

use App\Models\ReservedOperatingInitial;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class OiMatrix extends Component
{
    public string $search = '';

    public ?string $selected = null;

    public ?int $assignUserId = null;

    public string $reserveReason = '';

    public string $controllerSearch = '';

    public string $panelSearch = '';

    #[Url(as: 'controller')]
    public ?int $assigningUserId = null;

    public function mount(): void
    {
        // Arrived via ?controller=<cid> (e.g. "Assign OI" on the users page): only honour eligible controllers.
        if ($this->assigningUserId !== null) {
            $eligible = auth()->user()?->can('manage users') && User::where('rostered', true)
                ->whereNull('operating_initials')
                ->whereKey($this->assigningUserId)
                ->exists();

            if (! $eligible) {
                $this->assigningUserId = null;
            }
        }
    }

    public function pickController(int $userId): void
    {
        $this->authorizeManage(requireSelection: false);

        $this->assigningUserId = User::where('rostered', true)
            ->whereNull('operating_initials')
            ->whereKey($userId)
            ->value('id');
        $this->controllerSearch = '';
        $this->closePanel();
    }

    public function clearController(): void
    {
        $this->reset(['assigningUserId', 'controllerSearch']);
        $this->resetValidation();
    }

    public function select(string $initials): void
    {
        $initials = strtoupper($initials);

        if (! self::isValidInitials($initials)) {
            return;
        }

        // Controller-first flow: clicking a vacant OI assigns it to the chosen controller.
        if ($this->assigningUserId !== null
            && ! ReservedOperatingInitial::isReserved($initials)
            && ! User::where('operating_initials', $initials)->exists()) {
            $this->selected = $initials;
            $this->assignUserId = $this->assigningUserId;
            $this->assign();

            return;
        }

        $this->selected = $initials;
        $this->reset(['assignUserId', 'reserveReason', 'panelSearch']);
        $this->resetValidation();
    }

    public function closePanel(): void
    {
        $this->reset(['selected', 'assignUserId', 'reserveReason', 'panelSearch']);
        $this->resetValidation();
    }

    public function assign(): void
    {
        $this->authorizeManage();

        $this->validate(
            ['assignUserId' => 'required|integer|exists:users,id'],
            ['assignUserId.required' => 'Please choose a controller.'],
        );

        $initials = $this->selected;

        if (ReservedOperatingInitial::isReserved($initials)) {
            $this->addError('assignUserId', 'These operating initials are reserved and cannot be assigned.');

            return;
        }

        if (User::where('operating_initials', $initials)->exists()) {
            $this->addError('assignUserId', 'These operating initials are already assigned.');

            return;
        }

        $user = User::where('rostered', true)->find($this->assignUserId);

        if (! $user) {
            $this->addError('assignUserId', 'Only rostered controllers can be assigned operating initials.');

            return;
        }

        if ($user->operating_initials !== null) {
            $this->addError('assignUserId', "{$user->name} already has operating initials.");

            return;
        }

        try {
            $user->operating_initials = $initials;
            $user->save();
        } catch (UniqueConstraintViolationException) {
            // Another request claimed these initials between the check above and the save.
            $this->addError('assignUserId', 'These operating initials are already assigned.');

            return;
        }

        session()->flash('success', "{$initials} assigned to {$user->name}.");
        $this->reset(['assigningUserId', 'controllerSearch']);
        $this->closePanel();
    }

    public function unassign(): void
    {
        $this->authorizeManage();

        $user = User::where('operating_initials', $this->selected)->first();

        if ($user) {
            $user->operating_initials = null;
            $user->save();
            session()->flash('success', "{$this->selected} unassigned from {$user->name}.");
        }

        $this->closePanel();
    }

    public function reserve(): void
    {
        $this->authorizeManage();

        $this->validate(['reserveReason' => 'nullable|string|max:255']);

        $initials = $this->selected;

        if (User::where('operating_initials', $initials)->exists()) {
            $this->addError('reserveReason', 'Unassign these operating initials before reserving them.');

            return;
        }

        if (ReservedOperatingInitial::isReserved($initials)) {
            $this->addError('reserveReason', 'These operating initials are already reserved.');

            return;
        }

        $reserved = ReservedOperatingInitial::create([
            'initials' => $initials,
            'reason' => trim($this->reserveReason) ?: null,
            'reserved_by' => auth()->id(),
        ]);

        // Shaped like the model-level entries so the audit log viewer/export renders it.
        activity()
            ->causedBy(auth()->user())
            ->performedOn($reserved)
            ->event('created')
            ->withProperties(['attributes' => ['initials' => $initials, 'reason' => $reserved->reason]])
            ->log("Reserved operating initials {$initials}");

        session()->flash('success', "{$initials} reserved.");
        $this->closePanel();
    }

    public function release(): void
    {
        $this->authorizeManage();

        $reserved = ReservedOperatingInitial::find($this->selected);

        if ($reserved) {
            $reserved->delete();

            activity()
                ->causedBy(auth()->user())
                ->performedOn($reserved)
                ->event('deleted')
                ->withProperties(['old' => ['initials' => $reserved->initials, 'reason' => $reserved->reason]])
                ->log("Released reserved operating initials {$reserved->initials}");

            session()->flash('success', "{$this->selected} released and available to assign.");
        }

        $this->closePanel();
    }

    /**
     * Rostered controllers without an OI matching a name / CID search (empty search returns nothing).
     *
     * @return Collection<int, User>
     */
    private function searchEligible(string $search): Collection
    {
        $search = trim($search);

        if ($search === '') {
            return collect();
        }

        $term = '%'.mb_strtolower($search).'%';

        return User::where('rostered', true)
            ->whereNull('operating_initials')
            ->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$term])
                    ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$term]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get();
    }

    public function chooseForPanel(int $userId): void
    {
        $this->authorizeManage();

        $this->assignUserId = User::where('rostered', true)
            ->whereNull('operating_initials')
            ->whereKey($userId)
            ->value('id');
        $this->panelSearch = '';
    }

    public function clearPanelChoice(): void
    {
        $this->reset(['assignUserId', 'panelSearch']);
    }

    private function authorizeManage(bool $requireSelection = true): void
    {
        abort_unless(auth()->user()?->can('manage users'), 403);

        abort_if($requireSelection && ! self::isValidInitials((string) $this->selected), 422);
    }

    private static function isValidInitials(string $initials): bool
    {
        return (bool) preg_match('/^[A-Z]{2}$/', $initials);
    }

    /**
     * Every possible OI, AA through ZZ.
     *
     * @return Collection<int, string>
     */
    private function allInitials(): Collection
    {
        return collect(range('A', 'Z'))->flatMap(
            fn ($first) => collect(range('A', 'Z'))->map(fn ($second) => $first.$second)
        );
    }

    public function render()
    {
        $holders = User::whereNotNull('operating_initials')->get()->keyBy('operating_initials');
        $reserved = ReservedOperatingInitial::with('reservedBy')->get()->keyBy('initials');
        $homeFacility = config('app.vatusa_facility');
        $search = strtoupper(trim($this->search));

        $cells = $this->allInitials()->map(function (string $initials) use ($holders, $reserved, $homeFacility, $search) {
            $holder = $holders->get($initials);
            $reservation = $reserved->get($initials);

            $state = match (true) {
                $holder !== null && strcasecmp((string) $holder->facility, (string) $homeFacility) === 0 => 'home',
                $holder !== null => 'visitor',
                $reservation !== null => 'reserved',
                default => 'vacant',
            };

            $matches = $search === ''
                || str_contains($initials, $search)
                || ($holder && str_contains(strtoupper($holder->name.' '.$holder->id), $search))
                || ($reservation && str_contains(strtoupper((string) $reservation->reason), $search));

            return compact('initials', 'state', 'holder', 'reservation', 'matches');
        });

        $selectedCell = $this->selected ? $cells->firstWhere('initials', $this->selected) : null;

        $controllerResults = $this->searchEligible($this->controllerSearch);

        $panelResults = $selectedCell && $selectedCell['state'] === 'vacant'
            ? $this->searchEligible($this->panelSearch)
            : collect();

        $panelUser = $this->assignUserId ? User::find($this->assignUserId) : null;

        $assigningUser = $this->assigningUserId ? User::find($this->assigningUserId) : null;

        $suggestion = null;
        if ($assigningUser) {
            $suggestion = strtoupper(mb_substr($assigningUser->first_name, 0, 1).mb_substr($assigningUser->last_name, 0, 1));
            if (! preg_match('/^[A-Z]{2}$/', $suggestion) || $cells->firstWhere('initials', $suggestion)['state'] !== 'vacant') {
                $suggestion = null;
            }
        }

        return view('livewire.oi-matrix', [
            'controllerResults' => $controllerResults,
            'assigningUser' => $assigningUser,
            'suggestion' => $suggestion,
            'cells' => $cells,
            'selectedCell' => $selectedCell,
            'panelResults' => $panelResults,
            'panelUser' => $panelUser,
            'counts' => $cells->countBy('state'),
            'canManage' => (bool) auth()->user()?->can('manage users'),
        ]);
    }
}
