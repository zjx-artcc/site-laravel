<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualContributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ManualContributorController extends Controller
{
    public function index()
    {
        return view('admin.contributors.index', [
            'contributors' => ManualContributor::orderBy('github_username')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'github_username' => 'nullable|string|max:39|unique:manual_contributors,github_username',
            'display_name' => 'required_without:github_username|nullable|string|max:100',
            'section' => 'required|in:main,fork,contributor,beta',
            'note' => 'nullable|string|max:100',
        ]);

        $contributor = ManualContributor::create($validated);
        Cache::forget('github_contributors');

        Log::info('Contributor added', [
            'contributor_id' => $contributor->id,
            'github_username' => $validated['github_username'] ?? null,
            'display_name' => $validated['display_name'] ?? null,
            'section' => $validated['section'],
            'added_by' => Auth::user()->id,
        ]);

        $label = $validated['github_username'] ? "@{$validated['github_username']}" : ($validated['display_name'] ?? 'Contributor');

        return redirect()->back()->with('success', "{$label} added as a contributor.");
    }

    public function destroy(ManualContributor $contributor)
    {
        Log::info('Contributor removed', [
            'contributor_id' => $contributor->id,
            'github_username' => $contributor->github_username,
            'display_name' => $contributor->display_name,
            'section' => $contributor->section,
            'removed_by' => Auth::user()->id,
        ]);

        $contributor->delete();
        Cache::forget('github_contributors');

        $label = $contributor->github_username ? "@{$contributor->github_username}" : ($contributor->display_name ?? 'Contributor');

        return redirect()->back()->with('success', "{$label} removed.");
    }
}
