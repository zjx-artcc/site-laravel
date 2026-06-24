@extends('layouts.admin')

@section('title', 'Document Categories')

@section('body')
    <div class="w-full px-4 sm:px-6 lg:px-8">

        <a href="{{ route('admin.publications.index') }}" class="btn btn-ghost btn-sm mb-4 gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Documents
        </a>

        {{-- Toolbar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <h1 class="text-xl font-semibold">Document Categories</h1>
            <a href="{{ route('admin.publications.categories.create') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                New Category
            </a>
        </div>


        @if(session('error'))
            <div class="alert alert-error mb-4">
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($categories->isEmpty())
            <x-card-component>
                <p class="text-base-content/60">No categories yet. Create one to get started.</p>
            </x-card-component>
        @else
            <x-card-component>
                <div class="overflow-x-auto mt-2">
                    <table class="table table-zebra table-md border-2 border-base-300 w-full">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Title</th>
                                <th>Documents</th>
                                <th>Description</th>
                                <th>
                                    In Mobile Nav Menu
                                    <p class="text-xs font-normal normal-case opacity-50 mt-0.5">Click to show/hide</p>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categories as $category)
                                <tr>
                                    <td class="border-r border-base-300">{{ $category->display_order }}</td>
                                    <td class="border-r border-base-300 font-medium">{{ $category->title }}</td>
                                    <td class="border-r border-base-300">
                                        <span class="badge badge-outline badge-sm">{{ $category->publications_count }}</span>
                                    </td>
                                    <td class="border-r border-base-300 text-sm max-w-xs">{{ $category->description }}</td>
                                    <td class="border-r border-base-300">
                                        <form action="{{ route('admin.publications.categories.toggle-nav', $category->id) }}"
                                              method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="badge {{ $category->show_in_nav ? 'badge-success' : 'badge-ghost' }} badge-sm cursor-pointer border-0"
                                                    title="{{ $category->show_in_nav ? 'Visible — click to hide' : 'Hidden — click to show' }}">
                                                {{ $category->show_in_nav ? 'Visible' : 'Hidden' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('admin.publications.categories.edit', $category->id) }}"
                                               class="btn btn-primary btn-sm">Edit</a>
                                            <form action="{{ route('admin.publications.categories.destroy', $category->id) }}"
                                                  method="POST"
                                                  class="inline"
                                                  onsubmit="return confirm('Delete category \'{{ addslashes($category->title) }}\'? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-error btn-sm"
                                                        @disabled($category->publications_count > 0)>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-base-content/50 mt-3">
                    Categories that still contain documents cannot be deleted. Move or delete the documents first.
                </p>
            </x-card-component>
        @endif

    </div>
@endsection
