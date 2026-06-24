<div
    @if($eventClickEnabled)
        wire:click.stop="onEventClick('{{ $event['id']  }}')"
    @endif
    class="bg-white border-2 border-gray-400 rounded-xl py-3 px-3 lg:py-1 lg:px-2 shadow-md cursor-pointer hover:shadow-lg transition-shadow">

    <div class="flex items-center justify-center min-h-12 lg:min-h-0">
        <p class="text-xs sm:text-sm font-bold text-gray-900 leading-snug text-center">
            {{ $event['title'] }}
        </p>
    </div>
    @if($event['description'])
        <p class="hidden lg:block text-xs text-gray-600 mt-1 overflow-hidden text-ellipsis whitespace-nowrap">
            {{ explode('.', $event['description'])[0] }}.
        </p>
    @endif
</div>
