<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventRegistration extends Component
{
    // The data will be position and notes
    // IDEA FOR LATER: Color code system on the events team side to show if a position has been taken or not. Also stats to see the
    // most wanted position?

    // LATER: Come back and clean up code

    // --------Functionality------------
    // Each event will pass itself to this component. Then,
    // get the position presets for the event that is being registered for.
    // This will be passed to the view. That way, the user can select their position
    // from the list.

    public $notes;

    public Event $event;

    public $positionPreset;

    public $positions = [];

    public $selectedPosition;

    public $start;

    public $end;

    public $submitted = false;

    public function mount(Event $event)
    {
        $authenticatedUser = Auth::user();

        $this->event = $event;
        $this->positions = $event->presetPositions ?? [];
        $this->selectedPosition = '';

        // Display event times as UTC
        $this->start = $event->start
            ->utc()
            ->format('Y-m-d\TH:i');

        $this->end = $event->end
            ->utc()
            ->format('Y-m-d\TH:i');

        $registration = EventPosition::where('user_id', $authenticatedUser->id)
            ->where('event_id', $event->id)
            ->first();

        if ($registration) {
            $this->selectedPosition = $registration->requested_position;

            $this->start = $registration->start
                ->utc()
                ->format('Y-m-d\TH:i');

            $this->end = $registration->end
                ->utc()
                ->format('Y-m-d\TH:i');

            $this->notes = $registration->notes;
            $this->submitted = true;
        }
    }

    public function store()
    {
        $authenticatedUser = Auth::user();

        $validated = $this->validate([
            'selectedPosition' => 'required|string',
            'notes' => 'nullable|string|max:500',
            'start' => [
                'required',
                'date',
                'after_or_equal:'.$this->event->start->toDateTimeString(),
                'before_or_equal:'.$this->event->end->toDateTimeString(),
            ],
            'end' => [
                'required',
                'date',
                'after:start',
                'before_or_equal:'.$this->event->end->toDateTimeString(),
            ],
        ], [
            'selectedPosition.required' => 'Please select a position.',
            'start.required' => 'Please select a start time.',
            'end.required' => 'Please select an end time.',
            'end.after' => 'The end time must be after the start time.',
            'start.before_or_equal' => 'The start time cannot be after the event ends.',
            'end.before_or_equal' => 'The end time cannot be after the event ends.',
        ]);

        $startTime = Carbon::parse($this->start, 'UTC');
        $endTime = Carbon::parse($this->end, 'UTC');

        if ($startTime->diffInMinutes($endTime) < 30) {
            $this->addError(
                'end',
                'Your registration must be at least 30 minutes long.'
            );

            return;
        }

        EventPosition::create([
            'user_id' => $authenticatedUser->id,
            'event_id' => $this->event->id,
            'requested_position' => $validated['selectedPosition'],
            'start' => Carbon::parse($validated['start'], 'UTC'),
            'end' => Carbon::parse($validated['end'], 'UTC'),
            'notes' => $validated['notes'],
        ]);

        $this->submitted = true;
    }

    public function destroy()
    {
        $authenticatedUser = Auth::user();
        $registration = EventPosition::where('user_id', $authenticatedUser->id)->where('event_id', $this->event->id)->first();
        $registration->delete();
        $this->submitted = false;
    }

    public function render()
    {
        return view('livewire.event-registration');
    }
}
