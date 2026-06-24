@extends('layouts.admin')

@section('title', 'Edit Document')

@section('body')
    <div class="w-full px-4 sm:px-6 lg:px-8 max-w-3xl">

        <a href="{{ route('admin.publications.index') }}" class="btn btn-ghost btn-sm mb-4 gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Documents
        </a>

        <form method="POST" action="{{ route('admin.publications.update', $document->id) }}" enctype="multipart/form-data" class="flex flex-col gap-5">
            @csrf
            @method('PUT')

            {{-- Document Details --}}
            <div class="collapse collapse-open bg-base-100 border border-base-300">
                <input type="checkbox" checked />
                <div class="collapse-title font-semibold">Document Details</div>
                <div class="collapse-content flex flex-col gap-4">

                    <div>
                        <label for="name" class="label">Document Name <span class="text-error">*</span></label>
                        <input id="name"
                               name="name"
                               type="text"
                               required
                               placeholder="e.g. ZJX ARTCC SOP"
                               value="{{ old('name', $document->name) }}"
                               class="input input-bordered w-full @error('name') input-error @enderror" />
                        @error('name')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="label">Description <span class="text-error">*</span></label>
                        <textarea id="description"
                                  name="description"
                                  required
                                  rows="3"
                                  placeholder="Brief description of this document..."
                                  class="textarea textarea-bordered w-full @error('description') textarea-error @enderror">{{ old('description', $document->description) }}</textarea>
                        @error('description')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Category & Version --}}
            <div class="collapse collapse-open bg-base-100 border border-base-300">
                <input type="checkbox" checked />
                <div class="collapse-title font-semibold">Category & Version</div>
                <div class="collapse-content flex flex-col gap-4">

                    <div>
                        <label for="publication_category_id" class="label">Category <span class="text-error">*</span></label>
                        <select id="publication_category_id"
                                name="publication_category_id"
                                required
                                class="select select-bordered w-full @error('publication_category_id') select-error @enderror">
                            <option value="" disabled>Select a category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ (int) old('publication_category_id', $document->publication_category_id) === $category->id ? 'selected' : '' }}>
                                    {{ $category->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('publication_category_id')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="version" class="label">Version <span class="text-error">*</span></label>
                        <input id="version"
                               name="version"
                               type="text"
                               required
                               placeholder="e.g. v1.0"
                               value="{{ old('version', $document->version) }}"
                               class="input input-bordered w-full @error('version') input-error @enderror" />
                        @error('version')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label">Updated At (UTC)</label>
                        <div class="input input-bordered w-full flex items-center text-base-content/50 cursor-not-allowed select-none">
                            {{ $document->updated_at->utc()->format('D, d M Y H:i:s') }} GMT
                        </div>
                        <p class="text-xs text-base-content/50 mt-1">Automatically updated when changes are saved.</p>
                    </div>

                </div>
            </div>

            {{-- File Upload --}}
            <div class="collapse collapse-open bg-base-100 border border-base-300">
                <input type="checkbox" checked />
                <div class="collapse-title font-semibold">File</div>
                <div class="collapse-content flex flex-col gap-4">

                    <div>
                        <label class="label">Current File</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ $document->file_url }}" target="_blank" class="btn btn-outline btn-sm gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                View Current File
                            </a>
                            <span class="text-sm text-base-content/60">{{ $document->original_filename }}</span>
                        </div>
                    </div>

                    <div>
                        <label for="file" class="label">Replace File</label>
                        <input id="file"
                               name="file"
                               type="file"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.gz,.7z,.json,.xml,.txt"
                               class="file-input file-input-bordered w-full @error('file') file-input-error @enderror" />
                        <p class="text-xs text-base-content/50 mt-1">Leave empty to keep the current file. Accepted: PDF, Word, Excel, PowerPoint, PNG, JPG, ZIP, JSON, XML, TXT. Max 10 MB.</p>
                        @error('file')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.publications.index') }}" class="btn btn-ghost">Cancel</a>
            </div>

        </form>

        {{-- Danger Zone --}}
        <div class="mt-8">
            <x-card-component title="Danger Zone">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-3">
                    <div>
                        <p class="font-medium">Delete this document</p>
                        <p class="text-sm text-base-content/60">Permanently removes this document from the publications list.</p>
                    </div>
                    <form action="{{ route('admin.publications.destroy', $document->id) }}"
                          method="POST"
                          onsubmit="return confirm('Delete \'{{ addslashes($document->name) }}\'? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-sm shrink-0">Delete Document</button>
                    </form>
                </div>
            </x-card-component>
        </div>

    </div>
@endsection
