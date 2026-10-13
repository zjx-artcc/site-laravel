<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FeaturedField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventFieldController extends Controller
{
    public function index()
    {
        return view('event-fields.index', [
            'featuredFields' => FeaturedField::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('featured_fields', 'name')],
        ]);

        FeaturedField::create(['name' => strtoupper(trim($validated['name']))]);

        return redirect()->route('admin.events.event-fields.index')
            ->with('success', 'Event field added.');
    }

    public function destroy(FeaturedField $eventField)
    {
        // Events record their fields in the events.featured_fields JSON column, not
        // through the event_featured_field pivot, so that is what has to be checked.
        $inUse = Event::whereJsonContains('featured_fields', $eventField->name)->exists();

        if ($inUse) {
            return back()->with('error', 'This field is still used by an event and cannot be removed.');
        }

        $eventField->delete();

        return redirect()->route('admin.events.event-fields.index')
            ->with('success', 'Event field removed.');
    }
}
