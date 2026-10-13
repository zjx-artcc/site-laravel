@extends('layouts.main')

@section('title', 'Publications & Downloads')

@section('body')
    <div class="w-full px-4 sm:px-6 lg:px-8">

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
                            @if(filled($category->description))
                                <p class="text-base-content/60 text-sm mt-0.5">{{ $category->description }}</p>
                            @endif
                        </div>

                        {{-- Documents list --}}
                        <div class="flex flex-col">
                            @forelse($category->publications as $doc)
                                <a href="{{ $doc->file_url }}"
                                   target="_blank"
                                   class="group flex items-center gap-4 py-3 px-3 -mx-1 my-1 rounded-lg cursor-pointer border border-base-300 bg-base-100 transition-colors hover:bg-base-200 hover:border-primary focus-visible:bg-base-200 focus-visible:border-primary">
                                    <div class="flex-1 min-w-0">
                                        <span class="font-medium text-base text-primary group-hover:underline">{{ $doc->name }}</span>
                                        @if(filled($doc->description))
                                            <p class="text-sm text-base-content/60 mt-0.5">{{ $doc->description }}</p>
                                        @endif
                                        <p class="text-xs text-base-content/40 mt-0.5">
                                            Updated {{ $doc->updated_at->utc()->format('D, d M Y H:i:s') }} GMT
                                        </p>
                                    </div>

                                    {{-- Says what clicking does. Matches the Content-Disposition the file route sends. --}}
                                    <span class="shrink-0 flex items-center gap-2 text-base font-semibold text-primary border-2 border-primary rounded-lg px-5 py-2.5 transition-colors group-hover:bg-primary group-hover:text-primary-content">
                                        @if($doc->opensInBrowser())
                                            View
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        @else
                                            Download
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        @endif
                                    </span>
                                </a>
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
