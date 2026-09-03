<div
    @if($eventClickEnabled)
        wire:click.stop="onEventClick('{{ $event['id']  }}')"
    @endif
    class="bg-base-100 border-2 border-base-300 rounded-xl py-3 px-3 lg:py-1 lg:px-2 shadow-md cursor-pointer hover:shadow-lg transition-shadow">

    <div class="flex items-center justify-center min-h-12 lg:min-h-0">
        <p class="text-xs sm:text-sm font-bold text-base-content leading-snug text-center">
            {{ $event['title'] }}
        </p>
    </div>
    @if($event['description'])
        <p class="hidden lg:block text-xs text-base-content/60 mt-1 overflow-hidden text-ellipsis whitespace-nowrap">
            {{ explode('.', $event['description'])[0] }}.
        </p>
    @endif
</div>
