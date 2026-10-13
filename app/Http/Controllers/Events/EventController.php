<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::where('start', '>=', now())
            ->where('hidden', false)
            ->where('archived', false)
            ->orderBy('start', 'asc')
            ->take(3)
            ->get();

        return view('events.index', ['events' => $events]);
    }

    public function show(string $id)
    {
        $event = Event::findOrFail($id);

        // Hiding an event has to hold for a direct link too, not just the calendar.
        // Staff who can manage events still need to preview it.
        if (($event->hidden || $event->archived) && ! Auth::user()?->hasPermissionTo('manage events')) {
            abort(404);
        }

        return view('events.show', [
            'event' => $event,
            'roster' => $event->published ? $this->buildRoster($event) : collect(),
        ]);
    }

    /**
     * The canonical position list, unioned with any assigned_position that has
     * drifted off it (legacy data, or an assignment made before a since-removed
     * position was dropped), so a real assignment is never hidden from the roster.
     *
     * @return Collection<int, array{position: string, assignees: Collection<int, array{user: ?User, start: ?Carbon, end: ?Carbon}>}>
     */
    private function buildRoster(Event $event): Collection
    {
        $event->load('positionRequests.user');

        $assignedByPosition = $event->positionRequests
            ->whereNotNull('assigned_position')
            ->groupBy(fn ($position) => strtoupper($position->assigned_position));

        $canonicalPositions = collect($event->presetPositions ?? []);

        $allPositions = $canonicalPositions
            ->merge($assignedByPosition->keys()->diff($canonicalPositions->map('strtoupper')))
            ->unique(fn ($position) => strtoupper($position));

        return $allPositions->map(fn ($position) => [
            'position' => $position,
            'assignees' => $assignedByPosition->get(strtoupper($position), collect())->map(fn ($registrant) => [
                'user' => $registrant->user,
                'start' => $registrant->assigned_start,
                'end' => $registrant->assigned_end,
            ]),
        ])->values();
    }
}
