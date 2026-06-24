<div>
    {{-- Month navigation header --}}
    <div class="flex items-center justify-between gap-2 mb-4">
        <button wire:click="previousMonth" class="btn btn-sm btn-ghost" aria-label="Previous month">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
        </button>

        <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-semibold select-none">{{ $monthLabel }}</h2>
            @unless($isCurrentMonth)
                <button wire:click="goToToday" class="btn btn-xs btn-primary" title="Go back to {{ now()->format('F Y') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h1m4-6v2m8-2v2m5 4h1M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Back to {{ now()->format('M Y') }}
                </button>
            @endunless
        </div>

        <button wire:click="nextMonth" class="btn btn-sm btn-ghost" aria-label="Next month">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
        </button>
    </div>

    {{-- ===== DESKTOP: Calendar grid (hidden on mobile) ===== --}}
    <div class="hidden sm:block">
        {{-- Day-of-week header --}}
        <div class="grid grid-cols-7 text-center text-xs font-semibold text-base-content/60 mb-1">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                <div class="py-1">{{ $dow }}</div>
            @endforeach
        </div>

        {{-- Weeks --}}
        @foreach($monthGrid as $week)
            <div class="grid grid-cols-7">
                @foreach($week as $day)
                    @php
                        $inMonth = $day->month === $monthStart->month;
                        $isToday = $day->isToday();
                        $dayKey = $day->format('Y-m-d');
                        $dayEvents = $eventsGrouped[$dayKey] ?? collect();
                    @endphp

                    <div class="border border-base-200 min-h-[5.5rem] lg:min-h-[6.5rem] p-1.5
                                {{ $inMonth ? 'bg-base-100' : 'bg-base-200/40' }}
                                {{ $isToday ? 'ring-2 ring-primary/40 ring-inset' : '' }}">
                        {{-- Day number --}}
                        <div class="text-xs font-medium {{ $inMonth ? 'text-base-content' : 'text-base-content/30' }} {{ $isToday ? 'text-primary font-bold' : '' }}">
                            {{ $day->format('j') }}
                        </div>

                        {{-- Events for this day --}}
                        <div class="mt-0.5 space-y-0.5 overflow-y-auto max-h-[4rem] lg:max-h-[5rem]">
                            @foreach($dayEvents as $event)
                                <a href="{{ route('events.show', $event->id) }}"
                                   class="block text-xs leading-tight px-1.5 py-0.5 rounded bg-primary/10 text-primary hover:bg-primary/20 transition truncate">
                                    {{ $event->title }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- ===== MOBILE: List view (visible only on small screens) ===== --}}
    <div class="sm:hidden space-y-2">
        @php
            $daysWithEvents = collect();
            foreach ($monthGrid as $week) {
                foreach ($week as $day) {
                    if ($day->month !== $monthStart->month) continue;
                    $dayKey = $day->format('Y-m-d');
                    $dayEvents = $eventsGrouped[$dayKey] ?? collect();
                    if ($dayEvents->isNotEmpty()) {
                        $daysWithEvents->push(['day' => $day, 'events' => $dayEvents]);
                    }
                }
            }
        @endphp

        @forelse($daysWithEvents as $item)
            <div class="card card-compact bg-base-100 border border-base-200">
                <div class="card-body p-3">
                    <h3 class="text-sm font-semibold {{ $item['day']->isToday() ? 'text-primary' : 'text-base-content' }}">
                        {{ $item['day']->format('l, M j') }}
                        @if($item['day']->isToday())
                            <span class="badge badge-primary badge-xs ml-1">Today</span>
                        @endif
                    </h3>
                    <div class="space-y-1.5 mt-1">
                        @foreach($item['events'] as $event)
                            <a href="{{ route('events.show', $event->id) }}"
                               class="flex items-start gap-2 p-2 rounded-lg bg-base-200/50 hover:bg-base-200 transition active:scale-[0.98]">
                                <div class="w-1 self-stretch rounded-full bg-primary shrink-0"></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate">{{ $event->title }}</p>
                                    <p class="text-xs text-base-content/60">
                                        {{ $event->start->format('g:i A') }}
                                        @if($event->end) – {{ $event->end->format('g:i A') }} @endif
                                    </p>
                                    @if($event->description)
                                        <p class="text-xs text-base-content/50 line-clamp-2 mt-0.5">{{ $event->description }}</p>
                                    @endif
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/30 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-10 text-base-content/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                <p class="text-sm">No events this month</p>
            </div>
        @endforelse
    </div>
</div>
