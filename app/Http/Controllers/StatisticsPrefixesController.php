<?php

namespace App\Http\Controllers;

use App\Models\StatisticsPrefixes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Log;
use Str;

class StatisticsPrefixesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('statistics-prefixes.index', ['prefixes' => StatisticsPrefixes::all()->sortBy('name')]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|max:5|min:2',
        ]);

        StatisticsPrefixes::firstOrCreate(['name' => $validated['name']]);
        Cache::forget('vatsim-statistics:prefixes');

        Log::info('Statistics prefix {prefix} added by user {user}', ['prefix' => $validated['name'], 'user' => Auth::user()->id]);

        return redirect()->back()->with('success', Str::upper($validated['name']).' added succesfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        StatisticsPrefixes::destroy($id);
        Cache::forget('vatsim-statistics:prefixes');

        Log::info('Statistics prefix {prefix} deleted by user {user}', ['prefix' => $id, 'user' => Auth::user()->id]);

        return redirect()->back()->with('success', 'Prefix removed successfully');
    }
}
