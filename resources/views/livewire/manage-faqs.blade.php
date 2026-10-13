<div>
    {{-- Public page header text --}}
    <div class="border border-base-300 rounded-box p-4 mb-6 bg-base-200/50">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-base-content/60 uppercase tracking-wide mb-1">Public page header</p>
                @unless($editingHeader)
                    <p class="font-bold">{{ $pageHeading }}</p>
                    <p class="text-sm text-base-content/70 whitespace-pre-line">{{ $pageIntro ?: '— no intro text —' }}</p>
                @endunless
            </div>
            @unless($editingHeader)
                <button wire:click="$set('editingHeader', true)" class="btn btn-sm btn-info shrink-0">Edit header</button>
            @endunless
        </div>

        @if($editingHeader)
            <div class="mt-3 space-y-3">
                <div class="form-control">
                    <label class="label" for="pageHeading"><span class="label-text">Title</span></label>
                    <input id="pageHeading" type="text" wire:model="pageHeading" class="input input-bordered input-sm w-full">
                    @error('pageHeading') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="form-control">
                    <label class="label" for="pageIntro"><span class="label-text">Intro text</span></label>
                    <textarea id="pageIntro" wire:model="pageIntro" rows="3" class="textarea textarea-bordered w-full text-sm"></textarea>
                    @error('pageIntro') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-2">
                    <button wire:click="savePageHeader" class="btn btn-primary btn-sm">Save header</button>
                    <button wire:click="cancelHeader" class="btn btn-ghost btn-sm">Cancel</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Header: stats + actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <span class="badge badge-neutral badge-lg">{{ $total }} total</span>
            <span class="badge badge-success badge-lg">{{ $publishedCount }} published</span>
            <span class="badge badge-ghost badge-lg">{{ $total - $publishedCount }} draft</span>
        </div>
        <div class="flex items-center gap-2">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search FAQs..."
                class="input input-bordered input-sm w-56"
            >
            <button wire:click="create" class="btn btn-accent btn-sm">+ New FAQ</button>
        </div>
    </div>

    {{-- Grouped list --}}
    @forelse($groupedFaqs as $category => $faqs)
        <div class="mb-6">
            <div class="flex items-center flex-wrap gap-2 mb-2">
                @if($renamingCategory === $category)
                    <input type="text" wire:model="categoryNewName"
                           wire:keydown.enter="saveRename" wire:keydown.escape="cancelRename"
                           class="input input-bordered input-xs w-56" autofocus>
                    <button wire:click="saveRename" class="btn btn-xs btn-primary">Save</button>
                    <button wire:click="cancelRename" class="btn btn-xs btn-ghost">Cancel</button>
                    @error('categoryNewName') <span class="text-error text-xs">{{ $message }}</span> @enderror
                @else
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/60">
                        {{ $category }} <span class="text-base-content/40">({{ $faqs->count() }})</span>
                    </h3>
                    <button wire:click="startRename(@js($category))" class="btn btn-ghost btn-xs">Rename</button>
                    <button wire:click="deleteCategory(@js($category))"
                            wire:confirm="Delete the '{{ $category }}' category and all {{ $faqs->count() }} FAQ(s) in it? This cannot be undone."
                            class="btn btn-ghost btn-xs text-error">Delete</button>
                @endif
            </div>

            <div class="border border-base-300 rounded-box divide-y divide-base-200 overflow-hidden">
                @foreach($faqs as $i => $faq)
                    <div class="flex items-center gap-3 px-3 py-2 hover:bg-base-200/60">
                        {{-- Reorder --}}
                        <div class="flex flex-col">
                            <button wire:click="moveUp({{ $faq->id }})"
                                    class="btn btn-ghost btn-xs px-1 leading-none"
                                    @disabled($i === 0) title="Move up">▲</button>
                            <button wire:click="moveDown({{ $faq->id }})"
                                    class="btn btn-ghost btn-xs px-1 leading-none"
                                    @disabled($i === $faqs->count() - 1) title="Move down">▼</button>
                        </div>

                        {{-- Question + meta --}}
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ $faq->question }}</p>
                            <p class="text-xs text-base-content/50">Updated {{ $faq->updated_at->utc()->format('d/m/Y Hi') }}z</p>
                        </div>

                        {{-- Status --}}
                        <div class="shrink-0">
                            @if($faq->is_published)
                                <span class="badge badge-success">Published</span>
                            @else
                                <span class="badge badge-ghost">Draft</span>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="shrink-0 flex items-center gap-1">
                            <button wire:click="edit({{ $faq->id }})" class="btn btn-xs btn-info">Edit</button>
                            <button wire:click="togglePublished({{ $faq->id }})" class="btn btn-xs btn-ghost">
                                {{ $faq->is_published ? 'Unpublish' : 'Publish' }}
                            </button>
                            <button wire:click="delete({{ $faq->id }})" wire:confirm="Delete this FAQ? This cannot be undone." class="btn btn-xs btn-error">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-center text-base-content/60 py-10 border border-dashed border-base-300 rounded-box">
            @if(trim($search) !== '')
                No FAQs match "{{ $search }}".
            @else
                No FAQs yet. Click <strong>+ New FAQ</strong> to add your first one.
            @endif
        </div>
    @endforelse

    {{-- Create / Edit modal --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="bg-base-100 rounded-box shadow-xl w-full max-w-3xl my-8" wire:key="faq-form">
                <div class="flex items-center justify-between border-b border-base-300 px-5 py-3">
                    <h3 class="font-semibold text-lg">{{ $editingId ? 'Edit FAQ' : 'New FAQ' }}</h3>
                    <button wire:click="cancel" class="btn btn-ghost btn-sm btn-circle">✕</button>
                </div>

                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="form-control md:col-span-2"
                             x-data="{ isNew: @js($category === '__new__') }">
                            <label class="label" for="category"><span class="label-text">Category</span></label>
                            <select id="category" wire:model.live="category"
                                    x-on:change="isNew = ($event.target.value === '__new__')"
                                    class="select select-bordered select-sm w-full">
                                @foreach($categories as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                                <option value="__new__">+ Add new category…</option>
                            </select>

                            <div x-show="isNew" x-cloak class="mt-2">
                                <input type="text" wire:model="newCategory" placeholder="New category name"
                                       class="input input-bordered input-sm w-full"
                                       x-effect="if (isNew) $nextTick(() => $el.focus())">
                                @error('newCategory') <span class="text-error text-xs">{{ $message }}</span> @enderror
                            </div>

                            @error('category') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-2 mt-7">
                                <input type="checkbox" wire:model="is_published" class="checkbox checkbox-sm">
                                <span class="label-text">Published</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-control mt-3">
                        <label class="label" for="question"><span class="label-text">Question</span></label>
                        <input id="question" type="text" wire:model="question" class="input input-bordered input-sm w-full">
                        @error('question') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Answer editor --}}
                    <div class="form-control mt-3"
                         wire:ignore
                         wire:key="faq-answer-editor-{{ $editingId ?? 'new' }}"
                         x-data="{
                            quill: null,
                            init() {
                                this.quill = new Quill(this.$refs.editor, {
                                    theme: 'snow',
                                    modules: {
                                        toolbar: [['bold', 'italic', 'underline', 'strike'], ['link'], [{list: 'ordered'}, {list: 'bullet'}]],
                                    },
                                });
                                new QuillMarkdown(this.quill);
                                this.quill.root.innerHTML = @js($answer);
                                this.quill.on('text-change', () => {
                                    $wire.set('answer', this.quill.root.innerHTML);
                                });
                            },
                         }">
                        <label class="label" for="answer"><span class="label-text">Answer</span></label>
                        <div x-ref="editor" class="bg-base-100 text-base-content min-h-50"></div>
                        <p class="text-xs text-base-content/70 mt-2">
                            Formatting shortcuts work as you type (e.g. <code>**bold**</code>, <code>- list item</code>), or use the toolbar.
                        </p>
                    </div>
                    @error('answer') <span class="text-error text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-2 border-t border-base-300 px-5 py-3">
                    <button wire:click="cancel" class="btn btn-ghost btn-sm">Cancel</button>
                    <button wire:click="save" class="btn btn-primary btn-sm">{{ $editingId ? 'Update' : 'Create' }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
