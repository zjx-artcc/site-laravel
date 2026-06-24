<?php

namespace App\Livewire;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Collection;
use App\Models\Event;

class EventsCalendar extends Component
{
    public int $currentYear;
    public int $currentMonth;

    public function mount(): void
    {
        $this->currentYear = now()->year;
        $this->currentMonth = now()->month;
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentYear = $date->year;
        $this->currentMonth = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentYear = $date->year;
        $this->currentMonth = $date->month;
    }

    public function goToToday(): void
    {
        $this->currentYear = now()->year;
        $this->currentMonth = now()->month;
    }

    private function monthGrid(): Collection
    {
        $start = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $end = $start->copy()->endOfMonth();

        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SATURDAY);

        $days = collect();
        $current = $gridStart->copy();

        while ($current->lte($gridEnd)) {
            $days->push($current->copy());
            $current->addDay();
        }

        return $days->chunk(7);
    }

    private function events(): Collection
    {
        $start = Carbon::create($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return Event::where('start', '>=', $start)
            ->where('start', '<=', $end)
            ->orderBy('start')
            ->get();
    }

    public function render()
    {
        $monthStart = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $events = $this->events();

        $eventsGrouped = $events->groupBy(fn ($e) => $e->start->format('Y-m-d'));

        return view('livewire.events-calendar', [
            'monthLabel' => $monthStart->format('F Y'),
            'monthGrid' => $this->monthGrid(),
            'events' => $events,
            'eventsGrouped' => $eventsGrouped,
            'monthStart' => $monthStart,
            'isCurrentMonth' => now()->month === $this->currentMonth && now()->year === $this->currentYear,
        ]);
    }
}
