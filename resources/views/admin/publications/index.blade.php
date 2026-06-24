@extends('layouts.admin')

@section('title', 'Document Management')

@section('body')
    <div class="w-full px-4 sm:px-6 lg:px-8">

        {{-- Toolbar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.publications.create') }}" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    New Document
                </a>
                <a href="{{ route('admin.publications.categories.index') }}" class="btn btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                    Manage Categories
                </a>
            </div>
            <a href="{{ route('publications.index') }}" target="_blank" class="btn btn-outline btn-sm gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                View Public Page
            </a>
        </div>

        @if($categories->isEmpty())
            <x-card-component>
                <p class="text-base-content/60">No publication categories have been configured yet.</p>
            </x-card-component>
        @else
            <div class="flex flex-col gap-6">
                @foreach($categories as $category)
                    <x-card-component title="{{ $category->title }}">
                        @if($category->publications->isEmpty())
                            <p class="text-sm text-base-content/50 mt-2">No documents in this category yet.</p>
                        @else
                            <div class="overflow-x-auto mt-2">
                                <table class="table table-zebra table-md border-2 border-base-300 w-full">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Version</th>
                                            <th>Updated At (UTC)</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($category->publications as $doc)
                                            <tr>
                                                <td class="border-r border-base-300 font-medium">{{ $doc->name }}</td>
                                                <td class="border-r border-base-300">
                                                    <span class="badge badge-outline badge-sm">{{ $doc->version }}</span>
                                                </td>
                                                <td class="border-r border-base-300 text-sm text-base-content/60">
                                                    {{ $doc->updated_at->utc()->format('D, d M Y') }}<br>
                                                    <span class="text-xs">{{ $doc->updated_at->utc()->format('H:i:s') }} GMT</span>
                                                </td>
                                                <td class="border-r border-base-300 text-sm max-w-xs">{{ $doc->description }}</td>
                                                <td>
                                                    <div class="flex flex-wrap gap-2">
                                                        <a href="{{ $doc->file_url }}"
                                                           target="_blank"
                                                           class="btn btn-ghost btn-sm gap-1"
                                                           title="View in browser">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            View
                                                        </a>
                                                        <a href="{{ route('admin.publications.edit', $doc->id) }}"
                                                           class="btn btn-primary btn-sm">
                                                            Edit
                                                        </a>
                                                        <form action="{{ route('admin.publications.destroy', $doc->id) }}"
                                                              method="POST"
                                                              class="inline"
                                                              onsubmit="return confirm('Delete \'{{ addslashes($doc->name) }}\'? This cannot be undone.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-error btn-sm">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-card-component>
                @endforeach
            </div>
        @endif

    </div>
@endsection
