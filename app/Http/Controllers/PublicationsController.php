<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\PublicationCategory;
use Illuminate\Support\Facades\Storage;

class PublicationsController extends Controller
{
    public function index()
    {
        $categories = PublicationCategory::with(['publications' => function ($query) {
            $query->orderBy('name');
        }])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        return view('publications.index', compact('categories'));
    }

    public function file(Publication $publication)
    {
        $disk = Storage::disk(Publication::DISK);

        abort_unless(
            $publication->file_path && $disk->exists($publication->file_path),
            404
        );

        // nosniff stops a browser second-guessing the type we sniffed and running a
        // mislabelled upload as HTML.
        $headers = [
            'Content-Type' => $publication->servedMimeType(),
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Anything a browser cannot render (vATIS profile JSON, legacy uploads) is
        // saved to disk rather than dumped on screen as raw text.
        if ($publication->opensInBrowser()) {
            return $disk->response($publication->file_path, $publication->original_filename, $headers);
        }

        return $disk->download($publication->file_path, $publication->original_filename, $headers);
    }
}
