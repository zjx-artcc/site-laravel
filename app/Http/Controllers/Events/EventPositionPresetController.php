<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\EventPositionPreset;
use Illuminate\Http\Request;

class EventPositionPresetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $positions = EventPositionPreset::all();

        return view('position-presets.index', compact('positions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $preset = new EventPositionPreset;

        return view('position-presets.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'positions' => 'required|string',
        ]);

        EventPositionPreset::create([
            'name' => $validated['name'],
            'positions' => $this->parsePositions($validated['positions']),
        ]);

        return redirect()->route('admin.events.position-presets.index')
            ->with('success', 'Preset created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $position = EventPositionPreset::find($id);

        return view('position-presets.edit', ['position' => $position]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $position = EventPositionPreset::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'positions' => 'required|string',
        ]);

        $position->update([
            'name' => $validated['name'],
            'positions' => $this->parsePositions($validated['positions']),
        ]);

        return redirect()->route('admin.events.position-presets.index')
            ->with('success', 'Preset updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $position = EventPositionPreset::find($id);
        $position->delete();

        return redirect()->route('admin.events.position-presets.index')->with('success', 'Preset deleted successfully');
    }

    /**
     * @return list<string>
     */
    private function parsePositions(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($position) => strtoupper(trim($position)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
