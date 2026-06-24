<div
    @if($pollMillis !== null && $pollAction !== null)
        wire:poll.{{ $pollMillis }}ms="{{ $pollAction }}"
    @elseif($pollMillis !== null)
        wire:poll.{{ $pollMillis }}ms
    @endif
>
    <div>
        @includeIf($beforeCalendarView)
    </div>

    <div class="w-full overflow-x-auto">
        <div class="w-full min-w-full">
            <!-- Day of Week Headers -->
            <div class="w-full flex flex-row bg-gray-300">
                @foreach($monthGrid->first() as $day)
                    @include($dayOfWeekView, ['day' => $day])
                @endforeach
            </div>

            <!-- Calendar Days -->
            @foreach($monthGrid as $week)
                <div class="w-full flex flex-row bg-gray-300">
                    @foreach($week as $day)
                        @include($dayView, [
                                'componentId' => $componentId,
                                'day' => $day,
                                'dayInMonth' => $day->isSameMonth($startsAt),
                                'isToday' => $day->isToday(),
                                'events' => $getEventsForDay($day, $events),
                            ])
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div>
        @includeIf($afterCalendarView)
    </div>
</div>
