<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\PublicationCategory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File as FileRule;

class AdminPublicationsController extends Controller
{
    private const DISK = Publication::DISK;

    private const DIRECTORY = 'documents';

    private const MAX_KB = 10240;

    public function index()
    {
        $categories = PublicationCategory::with(['publications' => function ($query) {
            $query->orderBy('name');
        }])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        return view('admin.publications.index', compact('categories'));
    }

    public function create()
    {
        $categories = PublicationCategory::orderBy('display_order')->orderBy('title')->get();

        return view('admin.publications.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePublication($request, fileRequired: true);

        $file = $request->file('file');
        $storedPath = $file->store(self::DIRECTORY, self::DISK);

        Publication::create([
            'publication_category_id' => $validated['publication_category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'file_path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        return redirect()
            ->route('admin.publications.index')
            ->with('success', 'Document created successfully.');
    }

    public function edit(int $id)
    {
        $document = Publication::findOrFail($id);
        $categories = PublicationCategory::orderBy('display_order')->orderBy('title')->get();

        return view('admin.publications.edit', compact('document', 'categories'));
    }

    public function update(Request $request, int $id)
    {
        $document = Publication::findOrFail($id);
        $validated = $this->validatePublication($request, fileRequired: false);

        $document->fill([
            'publication_category_id' => $validated['publication_category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            if ($document->file_path && Storage::disk(self::DISK)->exists($document->file_path)) {
                Storage::disk(self::DISK)->delete($document->file_path);
            }

            $document->file_path = $file->store(self::DIRECTORY, self::DISK);
            $document->original_filename = $file->getClientOriginalName();
            $document->file_size = $file->getSize();
        }

        $document->save();

        return redirect()
            ->route('admin.publications.index')
            ->with('success', 'Document updated successfully.');
    }

    public function destroy(int $id)
    {
        $document = Publication::findOrFail($id);

        if ($document->file_path && Storage::disk(self::DISK)->exists($document->file_path)) {
            Storage::disk(self::DISK)->delete($document->file_path);
        }

        $document->delete();

        return redirect()
            ->route('admin.publications.index')
            ->with('success', 'Document deleted successfully.');
    }

    private function validatePublication(Request $request, bool $fileRequired): array
    {
        return $request->validate([
            'publication_category_id' => ['required', Rule::exists('publication_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => [
                $fileRequired ? 'required' : 'nullable',
                FileRule::types(Publication::allowedMimeTypes())
                    ->extensions(array_keys(Publication::ALLOWED_TYPES))
                    ->max(self::MAX_KB),
                $this->contentMatchesExtension(),
            ],
        ]);
    }

    // types() and extensions() each check the allowlist alone, which still admits a JPEG named sop.pdf.
    private function contentMatchesExtension(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $extension = strtolower($value->getClientOriginalExtension());
            $permitted = Publication::ALLOWED_TYPES[$extension] ?? [];

            if (! in_array($value->getMimeType(), $permitted, true)) {
                $fail("The uploaded file's contents do not match its .{$extension} extension.");
            }
        };
    }
}
