<?php

namespace App\Http\Controllers;

use App\Models\PublicationCategory;
use Illuminate\Http\Request;

class AdminPublicationCategoriesController extends Controller
{
    public function index()
    {
        PublicationCategory::normalizeOrder();

        $categories = PublicationCategory::withCount('publications')
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        return view('admin.publications.categories.index', compact('categories'));
    }

    public function create()
    {
        $nextOrder = PublicationCategory::count();

        return view('admin.publications.categories.create', compact('nextOrder'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);
        $target = (int) $validated['display_order'];
        unset($validated['display_order']);

        $validated['show_in_nav'] = $request->boolean('show_in_nav', true);

        $new = PublicationCategory::create(array_merge($validated, [
            'display_order' => PublicationCategory::count() + 1,
        ]));

        PublicationCategory::repositionAndNormalize($new->id, $target);

        return redirect()
            ->route('admin.publications.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(int $id)
    {
        $category = PublicationCategory::findOrFail($id);

        return view('admin.publications.categories.edit', compact('category'));
    }

    public function update(Request $request, int $id)
    {
        $category = PublicationCategory::findOrFail($id);
        $validated = $this->validateCategory($request);
        $target = (int) $validated['display_order'];
        unset($validated['display_order']);

        $validated['show_in_nav'] = $request->boolean('show_in_nav');

        $category->update($validated);

        PublicationCategory::repositionAndNormalize($category->id, $target);

        return redirect()
            ->route('admin.publications.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function toggleNav(int $id)
    {
        $category = PublicationCategory::findOrFail($id);
        $category->update(['show_in_nav' => ! $category->show_in_nav]);

        return redirect()
            ->route('admin.publications.categories.index')
            ->with('success', "'{$category->title}' navbar visibility updated.");
    }

    public function destroy(int $id)
    {
        $category = PublicationCategory::withCount('publications')->findOrFail($id);

        if ($category->publications_count > 0) {
            return redirect()
                ->route('admin.publications.categories.index')
                ->with('error', "Cannot delete '{$category->title}' — it still contains {$category->publications_count} document(s). Move or delete them first.");
        }

        $category->delete();
        PublicationCategory::normalizeOrder();

        return redirect()
            ->route('admin.publications.categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    private function validateCategory(Request $request): array
    {
        return $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string'],
            'display_order' => ['required', 'integer', 'min:0'],
        ]);
    }
}
