<?php

namespace App\Livewire;

use App\Mail\EventPositionAssigned;
use App\Models\Event;
use App\Models\EventPosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class EventRegistrants extends Component
{
    public Collection $registrants;

    public Event $event;

    public array $positions = [];

    public array $assignments = [];

    /**
     * A snapshot of $assignments as loaded from the DB, so saveAll() can tell
     * which rows the admin actually touched without relying on Eloquent's
     * isDirty() — which would be true for every row once assigned_start/end
     * fall back to the event's own start/end (see mount()), even ones nobody
     * edited. Must be public: Livewire only persists public properties across
     * requests, and this needs to survive from mount() to saveAll().
     */
    public array $originalAssignments = [];

    public bool $publishedPositions = false;

    public function mount(Event $event)
    {
        $this->event = $event;
        $this->registrants = EventPosition::with('user')->where('event_id', $event->id)->get();
        $this->positions = $event->presetPositions ?? [];

        $this->publishedPositions = (bool) $this->event->published;

        foreach ($this->registrants as $registrant) {
            $this->assignments[$registrant->id] = [
                'assigned_start' => ($registrant->assigned_start ?? $registrant->start)->utc()->format('Y-m-d\TH:i'),
                'assigned_end' => ($registrant->assigned_end ?? $registrant->end)->utc()->format('Y-m-d\TH:i'),
                'assigned_position' => $registrant->assigned_position,
            ];
        }

        $this->originalAssignments = $this->assignments;
    }

    /**
     * Livewire's update endpoint does not inherit the mounting page's route
     * middleware, so every mutator below has to re-check its permission itself.
     */
    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->hasPermissionTo($permission), 403);
    }

    public function getCanAssignProperty(): bool
    {
        return (bool) auth()->user()?->can('assign event positions');
    }

    public function publishPositions()
    {
        $this->authorizePermission('publish events');

        $this->event->published = true;
        $this->event->save();

        $this->publishedPositions = $this->event->published;

        // Notify every assigned registrant who hasn't been told about their
        // current assignment yet — never everyone again on a repeat publish.
        $toNotify = EventPosition::with('user')
            ->where('event_id', $this->event->id)
            ->whereNotNull('assigned_position')
            ->whereNull('notified_at')
            ->get();

        foreach ($toNotify as $registrant) {
            $registrant->notified_at = now();
            $registrant->save();

            Mail::to($registrant->user->email)->queue(new EventPositionAssigned($registrant));
        }

        $this->dispatch('notify', type: 'success', message: 'Positions published.');
    }

    public function unpublishPositions()
    {
        $this->authorizePermission('publish events');

        $this->event->published = false;
        $this->event->save();
        $this->publishedPositions = $this->event->published;

        $this->dispatch('notify', type: 'warning', message: 'Positions unpublished.');
    }

    /**
     * Persist every registrant's assignment in one action, skipping rows
     * nobody has touched (never assigned in the DB, still blank on the form).
     */
    public function saveAll()
    {
        $this->authorizePermission('assign event positions');

        // Livewire component state can persist across requests within the same
        // page session, so re-check the current published value from the DB.
        $this->event->refresh();

        $errors = [];
        $savedCount = 0;

        foreach ($this->registrants as $registrant) {
            $result = $this->persistAssignment($registrant->id);

            if ($result === true) {
                $savedCount++;
            } elseif (is_string($result)) {
                $errors[] = $result;
            }
        }

        if (! empty($errors)) {
            $this->dispatch('notify', type: 'error', message: implode(' ', $errors));
        }

        if ($savedCount > 0) {
            $this->dispatch('notify', type: 'success', message: $savedCount === 1
                ? 'Saved 1 position assignment.'
                : "Saved {$savedCount} position assignments.");
        } elseif (empty($errors)) {
            $this->dispatch('notify', type: 'info', message: 'No changes to save.');
        }
    }

    /**
     * @return true|string|null true if persisted with a real change, an error
     *                          message if the row is invalid, or null if there
     *                          was nothing to do for this row.
     */
    private function persistAssignment(int $id): true|string|null
    {
        $data = $this->assignments[$id] ?? null;

        if (! $data) {
            return null;
        }

        // Nothing changed on the form for this row since it was loaded — skip
        // it rather than re-touching (and re-notifying about) an untouched row.
        if (($this->originalAssignments[$id] ?? null) === $data) {
            return null;
        }

        $validator = Validator::make($data, [
            'assigned_start' => ['required', 'date'],
            'assigned_end' => ['required', 'date', 'after:assigned_start'],
            'assigned_position' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            $registrant = $this->registrants->firstWhere('id', $id);
            $name = $registrant?->user
                ? "{$registrant->user->first_name} {$registrant->user->last_name}"
                : "registrant #{$id}";

            return "{$name}: ".implode(' ', $validator->errors()->all());
        }

        // $id always comes from $this->registrants (this event's own rows), so
        // this lookup can never cross into another event's data.
        $registrant = EventPosition::where('event_id', $this->event->id)->findOrFail($id);

        $registrant->assigned_start = $data['assigned_start'];
        $registrant->assigned_end = $data['assigned_end'];
        $registrant->assigned_position = $data['assigned_position'];
        $registrant->position_status = 'assigned';

        // Changed while unpublished — any prior notification is stale, so
        // clear it and let the next publishPositions() call re-notify.
        $registrant->notified_at = $this->event->published ? now() : null;
        $registrant->save();

        if ($this->event->published) {
            Mail::to($registrant->user->email)->queue(new EventPositionAssigned($registrant, isUpdate: true));
        }

        // Move the baseline forward so re-clicking Save All without further
        // edits in the same session is a no-op instead of re-notifying.
        $this->originalAssignments[$id] = $data;

        return true;
    }

    public function render()
    {
        return view('livewire.event-registrants');
    }
}
