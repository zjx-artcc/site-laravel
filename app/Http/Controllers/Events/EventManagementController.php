<?php

namespace App\Http\Controllers\Events;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPosition;
use App\Models\EventPositionPreset;
use App\Models\FeaturedField;
use App\Models\StaffingRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Stevebauman\Purify\Facades\Purify;

class EventManagementController extends Controller
{
    /**
     * Banner uploads are capped below php.ini's upload_max_filesize so that Laravel
     * reports the size problem itself instead of PHP silently rejecting the upload
     * and leaving the generic "The image failed to upload." message.
     *
     * SVG is deliberately excluded: banners are served straight from public storage,
     * and an SVG can carry script.
     */
    private const IMAGE_MAX_KILOBYTES = 8192;

    private const IMAGE_RULES = 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:'.self::IMAGE_MAX_KILOBYTES;

    private const IMAGE_MESSAGES = [
        'image.max' => 'The banner image must be 8 MB or smaller. Please compress or resize it and try again.',
        'image.mimes' => 'The banner image must be a JPEG, PNG, GIF, or WebP file.',
        'image.image' => 'The banner must be an image file.',
        'image.uploaded' => 'The banner image could not be uploaded. It is most likely larger than the 8 MB limit.',
    ];

    public function index()
    {
        $events = Event::all();

        return view('events.admin', ['events' => $events]);
    }

    public function toggleVisibility(Event $event)
    {
        if ($event->archived) {
            return back()->with('error', 'Archived events must remain hidden.');
        }

        $event->update([
            'hidden' => ! $event->hidden,
        ]);

        return back();
    }

    public function toggleArchived(Event $event)
    {
        if ($event->archived) {
            $event->update([
                'archived' => false,
                'archived_at' => null,
                'hidden' => true,
            ]);
        } else {
            $event->update([
                'archived' => true,
                'archived_at' => now(),
                'hidden' => true,
            ]);
        }

        return back();
    }

    public function togglePositionsLocked(Event $event)
    {
        $event->update([
            'positions_locked' => ! $event->positions_locked,
        ]);

        return back();
    }

    public function manage(Event $event)
    {
        $registrants = EventPosition::where('event_id', $event->id)->get();
        $mostRequestedPosition = $registrants
            ->groupBy('requested_position')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->keys()
            ->first();

        $positionsAccess = $this->positionsAccessFor();

        return view('events.manage', compact('event', 'registrants', 'mostRequestedPosition', 'positionsAccess'));
    }

    public function create(Request $request)
    {
        $event = new Event;
        $types = EventType::cases();
        $featuredFields = FeaturedField::orderBy('name')->pluck('name');
        $presetPositions = EventPositionPreset::orderBy('name')->pluck('name');

        // When an event is created off the back of a staffing request, carry the
        // requester's event name and description over so they aren't retyped.
        // The id is looked up rather than passing the text through the query
        // string, which keeps a 2000 character description out of the URL.
        $staffingRequest = $request->filled('staffing_request')
            ? StaffingRequest::find($request->query('staffing_request'))
            : null;

        return view('events.create', [
            'types' => $types,
            'featuredFields' => $featuredFields,
            'presetPositions' => $presetPositions,
            'prefillTitle' => $staffingRequest?->name ?? '',
            // The description is plain text; escape it and keep the requester's
            // line breaks, since the editor renders it as HTML.
            'prefillDescription' => $staffingRequest ? nl2br(e($staffingRequest->description)) : '',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'start' => 'required|date|before:end',
            'end' => ['required', 'date', 'after:start',
                function ($attribute, $value, $fail) use ($request) {
                    $start = Carbon::parse($request->start);
                    $end = Carbon::parse($value);

                    if ($start->diffInMinutes($end) < 60) {
                        $fail('The end time must be at least 1 hour after the start time.');
                    }
                }, ],
            'type' => [new Enum(EventType::class)],
            'featured_fields' => 'nullable|string',
            'presetPositions' => ['nullable', 'string', Rule::exists(EventPositionPreset::class, 'name')],
            'image' => self::IMAGE_RULES,
        ], self::IMAGE_MESSAGES);

        $presetName = $validated['presetPositions'] ?? null;
        $presetPositions = EventPositionPreset::where('name', $presetName)->first();
        $presetPositions = $presetPositions?->positions;

        $featuredFields = $this->parseFeaturedFields($validated['featured_fields'] ?? null);

        $event = Event::create([
            'title' => $validated['title'],
            'description' => Purify::clean($validated['description']),
            'start' => $validated['start'],
            'end' => $validated['end'],
            'type' => $validated['type'],
            'featured_fields' => $featuredFields,
            'presetPositions' => $presetPositions,
            'event_image_route' => null,
        ]);

        if ($request->hasFile('image')) {
            $event->event_image_route = $this->storeBanner($request, $event);
            $event->save();
        }

        return redirect()->route('admin.events.index')->with('success', 'Event created successfully!');
    }

    public function edit(Event $event)
    {
        $types = EventType::cases();
        $featuredFields = FeaturedField::orderBy('name')->pluck('name');
        $positionsAccess = $this->positionsAccessFor();

        return view('events.edit', [
            'event' => $event,
            'types' => $types,
            'featuredFields' => $featuredFields,
            'positionsAccess' => $positionsAccess,
        ]);
    }

    public function positions(Event $event)
    {
        $positionsAccess = $this->positionsAccessFor();

        abort_if($positionsAccess === 'hidden', 403);

        return view('events.positions', compact('event', 'positionsAccess'));
    }

    private function positionsAccessFor(): string
    {
        return match (true) {
            auth()->user()?->can('assign event positions') => 'full',
            auth()->user()?->can('manage events') => 'readonly',
            default => 'hidden',
        };
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'start' => 'required|date|before:end',
            'end' => ['required', 'date', 'after:start',
                function ($attribute, $value, $fail) use ($request) {
                    $start = Carbon::parse($request->start);
                    $end = Carbon::parse($value);

                    if ($start->diffInMinutes($end) < 60) {
                        $fail('The end time must be at least 1 hour after the start time.');
                    }
                }, ],
            'type' => [new Enum(EventType::class)],
            'featured_fields' => 'nullable|string',
            'image' => self::IMAGE_RULES,
        ], self::IMAGE_MESSAGES);

        $event = Event::findOrFail($id);
        $oldImagePath = $event->bannerStoragePath();

        $event->title = $validated['title'];
        $event->description = Purify::clean($validated['description']);
        $event->start = $validated['start'];
        $event->end = $validated['end'];
        $event->type = $validated['type'];
        $event->featured_fields = $this->parseFeaturedFields($validated['featured_fields'] ?? null);

        if ($request->hasFile('image')) {
            $event->event_image_route = $this->storeBanner($request, $event);

            // For the sake of storage, delete the old image
            if ($oldImagePath && $oldImagePath !== $event->event_image_route) {
                Storage::disk('public')->delete($oldImagePath);
            }
        }

        $event->save();

        return redirect()->route('admin.events.index')
            ->with('success', 'Event updated successfully.');
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);

        if ($path = $event->bannerStoragePath()) {
            Storage::disk('public')->delete($path);
        }

        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Event deleted successfully');
    }

    /**
     * @return list<string>
     */
    private function parseFeaturedFields(?string $featuredFields): array
    {
        if (blank($featuredFields)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $featuredFields)), 'strlen'));
    }

    /**
     * Derive the stored extension from the file's contents rather than the client-supplied
     * name, so an attacker cannot choose the extension the banner is served under.
     */
    private function storeBanner(Request $request, Event $event): string
    {
        $image = $request->file('image');
        $imageName = 'event_'.$event->id.'.'.$image->extension();

        return $image->storeAs('event', $imageName, 'public');
    }
}
