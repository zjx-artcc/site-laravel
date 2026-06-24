@extends('layouts.main')

@section('title', 'Publications & Downloads')

@section('body')
    <div class="w-full px-4 sm:px-6 lg:px-8">

        {{-- Intro --}}
        <p class="text-base-content/70 mb-6 max-w-3xl">
            Official vZJX documents including standard operating procedures, letters of agreement, training materials, quick reference guides, and facility maps. All documents are for simulation use only.
        </p>

        {{-- Category tabs (mobile: scroll horizontally; desktop: inline) --}}
        <div class="flex gap-2 flex-wrap mb-6">
            @foreach($categories as $category)
                <a href="#category-{{ $category->id }}"
                   class="btn btn-sm btn-outline">
                    {{ $category->title }}
                </a>
            @endforeach
        </div>

        {{-- Categories --}}
        <div class="flex flex-col gap-8">
            @foreach($categories as $category)
                <section id="category-{{ $category->id }}" class="scroll-mt-20">
                    <x-card-component>
                        {{-- Category header --}}
                        <div class="mb-4">
                            <h2 class="card-title text-xl sm:text-2xl">{{ $category->title }}</h2>
                            <p class="text-base-content/60 text-sm mt-0.5">{{ $category->description }}</p>
                        </div>

                        {{-- Documents list --}}
                        <div class="flex flex-col divide-y divide-base-200">
                            @forelse($category->publications as $doc)
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-base">{{ $doc->name }}</span>
                                            <span class="badge badge-outline badge-sm">{{ $doc->version }}</span>
                                        </div>
                                        <p class="text-sm text-base-content/60 mt-0.5">{{ $doc->description }}</p>
                                        <p class="text-xs text-base-content/40 mt-0.5">
                                            Updated {{ $doc->updated_at->utc()->format('D, d M Y H:i:s') }} GMT
                                        </p>
                                    </div>
                                    <div class="shrink-0 flex gap-2">
                                        @if($doc->isViewableInBrowser())
                                            <a href="{{ $doc->file_url }}"
                                               target="_blank"
                                               class="btn btn-outline btn-sm gap-1.5"
                                               aria-label="View {{ $doc->name }} in browser">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                View
                                            </a>
                                        @endif
                                        <a href="{{ $doc->file_url }}"
                                           download="{{ $doc->original_filename }}"
                                           class="btn btn-primary btn-sm gap-1.5"
                                           aria-label="Download {{ $doc->name }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Download
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50 py-3">No documents in this category yet.</p>
                            @endforelse
                        </div>
                    </x-card-component>
                </section>
            @endforeach
        </div>

    </div>
@endsection
