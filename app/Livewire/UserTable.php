<?php

namespace App\Livewire;

use App\Enums\ControllerRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class UserTable extends SortableTable
{
    use WithPagination;

    private const SORTABLE = ['last_name', 'id', 'operating_initials', 'rating', 'joined_at'];

    #[Url(except: '')]
    public string $search = '';

    /** rostered | not-rostered | all */
    #[Url(except: 'rostered')]
    public string $status = 'rostered';

    /** all | home | visitor */
    #[Url(except: 'all')]
    public string $membership = 'all';

    /** all | assigned | unassigned */
    #[Url(except: 'all')]
    public string $oi = 'all';

    #[Url(except: '')]
    public string $rating = '';

    #[Url(except: 'last_name')]
    public string $sortField = 'last_name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'membership', 'oi', 'rating'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        // The Joined column is only shown to managers, so don't allow sorting on it for others.
        if ($field === 'joined_at' && ! auth()->user()?->can('manage users')) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    public function filterOi(string $oi): void
    {
        $this->oi = $oi;
        $this->status = 'rostered';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'membership', 'oi', 'rating']);
        $this->resetPage();
    }

    private function applyHomeFilter(Builder $query, string $membership): void
    {
        $home = config('app.vatusa_facility');

        if ($membership === 'home') {
            $query->where('facility', $home);
        } elseif ($membership === 'visitor') {
            $query->where(fn ($q) => $q->whereNull('facility')->orWhere('facility', '!=', $home));
        }
    }

    public function render()
    {
        $canManage = (bool) auth()->user()?->can('manage users');
        $sortField = in_array($this->sortField, self::SORTABLE, true) && ($canManage || $this->sortField !== 'joined_at')
            ? $this->sortField
            : 'last_name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $query = User::query();

        match ($this->status) {
            'rostered' => $query->where('rostered', true),
            'not-rostered' => $query->where('rostered', false),
            default => null,
        };

        $this->applyHomeFilter($query, $this->membership);

        match ($this->oi) {
            'assigned' => $query->whereNotNull('operating_initials'),
            'unassigned' => $query->whereNull('operating_initials'),
            default => null,
        };

        if ($this->rating !== '' && ControllerRating::tryFrom((int) $this->rating) !== null) {
            $query->where('rating', (int) $this->rating);
        }

        $search = mb_strtolower(trim($this->search));
        if ($search !== '') {
            $term = '%'.$search.'%';

            $query->where(function (Builder $q) use ($term, $canManage) {
                $q->whereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(last_name || ', ' || first_name) LIKE ?", [$term])
                    ->orWhereRaw('CAST(id AS TEXT) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(operating_initials) LIKE ?', [$term]);

                if ($canManage) {
                    $q->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                }
            });
        }

        $users = $query
            ->orderBy($sortField, $direction)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);

        $rostered = User::where('rostered', true);

        return view('livewire.user-table', [
            'users' => $users,
            'canManage' => $canManage,
            'ratings' => ControllerRating::cases(),
            'stats' => [
                'rostered' => (clone $rostered)->count(),
                'home' => tap(clone $rostered, fn ($q) => $this->applyHomeFilter($q, 'home'))->count(),
                'visitors' => tap(clone $rostered, fn ($q) => $this->applyHomeFilter($q, 'visitor'))->count(),
                'missingOi' => (clone $rostered)->whereNull('operating_initials')->count(),
            ],
            'filtersActive' => $this->search !== '' || $this->status !== 'rostered' || $this->membership !== 'all'
                || $this->oi !== 'all' || $this->rating !== '',
        ]);
    }
}
