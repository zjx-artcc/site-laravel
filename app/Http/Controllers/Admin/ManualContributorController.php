<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualContributor;
use App\Support\PrivilegedAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        PrivilegedAction::record(PrivilegedAction::CONTRIBUTOR_ADDED, $contributor, $validated);

        $label = $validated['github_username'] ? "@{$validated['github_username']}" : ($validated['display_name'] ?? 'Contributor');

        return redirect()->back()->with('success', "{$label} added as a contributor.");
    }

    public function destroy(ManualContributor $contributor)
    {
        // Snapshot before the delete; afterwards the row is gone and the audit
        // entry would have nothing to describe but an ID.
        $snapshot = $contributor->only(['github_username', 'display_name', 'section', 'note']);

        $contributor->delete();
        Cache::forget('github_contributors');

        PrivilegedAction::record(PrivilegedAction::CONTRIBUTOR_REMOVED, $contributor, $snapshot);

        $label = $contributor->github_username ? "@{$contributor->github_username}" : ($contributor->display_name ?? 'Contributor');

        return redirect()->back()->with('success', "{$label} removed.");
    }
}
