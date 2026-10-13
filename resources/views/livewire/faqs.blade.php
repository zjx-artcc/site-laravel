<div class="max-w-4xl mx-auto">
    <div class="flex flex-col gap-1 mb-6">
        <h2 class="text-2xl font-bold">{{ $heading }}</h2>
        @if(trim($intro) !== '')
            <p class="text-base-content/70 whitespace-pre-line">{{ $intro }}</p>
        @endif
        @if($lastUpdated)
            <p class="text-sm text-base-content/60 mt-1">
                Last updated {{ \Illuminate\Support\Carbon::parse($lastUpdated)->utc()->format('d/m/Y Hi') }}z
            </p>
        @endif
    </div>

    <div class="mb-6">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search FAQs..."
            class="input input-bordered w-full"
        >
    </div>

    @forelse($groupedFaqs as $category => $faqs)
        <div class="mb-8">
            <h3 class="text-xl font-semibold mb-3 border-b border-base-300 pb-1">{{ $category }}</h3>

            @foreach($faqs as $faq)
                <div class="collapse collapse-arrow bg-base-100 border border-base-300 mb-2" wire:key="faq-{{ $faq->id }}">
                    <input type="checkbox" />
                    <div class="collapse-title font-medium">{{ $faq->question }}</div>
                    <div class="collapse-content">
                        <div class="pt-2 text-base-content/90"
                             wire:ignore
                             x-data
                             x-init="new Quill($el, { theme: 'snow', modules: { toolbar: false }, readOnly: true })">{!! $faq->answer !!}</div>
                        <p class="text-xs text-base-content/50 mt-3">
                            Updated {{ $faq->updated_at->utc()->format('d/m/Y Hi') }}z
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="text-center text-base-content/60 py-10">
            @if(trim($search) !== '')
                No FAQs match "{{ $search }}".
            @else
                No FAQs have been published yet. Check back soon!
            @endif
        </div>
    @endforelse
</div>
