<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\PublicationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminPublicationsController extends Controller
{
    private const DISK = 'public';
    private const DIRECTORY = 'documents';
    private const ALLOWED_MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,zip,gz,7z,json,xml,txt';
    private const MAX_KB = 10240;

    private const VIEWABLE_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'txt'];

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
            'name'                    => $validated['name'],
            'description'             => $validated['description'],
            'version'                 => $validated['version'],
            'file_path'               => $storedPath,
            'original_filename'       => $file->getClientOriginalName(),
            'file_size'               => $file->getSize(),
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
            'name'                    => $validated['name'],
            'description'             => $validated['description'],
            'version'                 => $validated['version'],
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
            'name'                    => ['required', 'string', 'max:255'],
            'description'             => ['required', 'string'],
            'version'                 => ['required', 'string', 'max:50'],
            'file'                    => [
                $fileRequired ? 'required' : 'nullable',
                'file',
                'mimes:' . self::ALLOWED_MIMES,
                'max:' . self::MAX_KB,
            ],
        ]);
    }
}
