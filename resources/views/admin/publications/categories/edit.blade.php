@extends('layouts.admin')

@section('title', 'Edit Category')

@section('body')
    <div class="w-full px-4 sm:px-6 lg:px-8 max-w-3xl">

        <a href="{{ route('admin.publications.categories.index') }}" class="btn btn-ghost btn-sm mb-4 gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Categories
        </a>

        <form method="POST" action="{{ route('admin.publications.categories.update', $category->id) }}" class="flex flex-col gap-5">
            @csrf
            @method('PUT')

            <div class="collapse collapse-open bg-base-100 border border-base-300">
                <input type="checkbox" checked />
                <div class="collapse-title font-semibold">Category Details</div>
                <div class="collapse-content flex flex-col gap-4">

                    <div>
                        <label for="title" class="label">Title <span class="text-error">*</span></label>
                        <input id="title"
                               name="title"
                               type="text"
                               required
                               value="{{ old('title', $category->title) }}"
                               class="input input-bordered w-full @error('title') input-error @enderror" />
                        @error('title')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="label">Description <span class="text-error">*</span></label>
                        <textarea id="description"
                                  name="description"
                                  required
                                  rows="3"
                                  class="textarea textarea-bordered w-full @error('description') textarea-error @enderror">{{ old('description', $category->description) }}</textarea>
                        @error('description')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="display_order" class="label">Display Order <span class="text-error">*</span></label>
                        <input id="display_order"
                               name="display_order"
                               type="number"
                               min="0"
                               required
                               value="{{ old('display_order', $category->display_order) }}"
                               class="input input-bordered w-full @error('display_order') input-error @enderror" />
                        <p class="text-xs text-base-content/50 mt-1">Lower numbers appear first. Moving to an existing position pushes others out of the way.</p>
                        @error('display_order')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <input id="show_in_nav"
                               name="show_in_nav"
                               type="checkbox"
                               value="1"
                               class="checkbox"
                               {{ old('show_in_nav', $category->show_in_nav) ? 'checked' : '' }} />
                        <label for="show_in_nav" class="label cursor-pointer">Show in mobile nav menu</label>
                    </div>

                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.publications.categories.index') }}" class="btn btn-ghost">Cancel</a>
            </div>

        </form>

    </div>
@endsection
