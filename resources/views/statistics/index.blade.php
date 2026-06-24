@extends('layouts.main')

@section('title', 'Controller Statistics')

@section('body')

    @php
        $periodLabel = $year === 0
            ? ($month === 0 ? 'All-Time Statistics' : \Illuminate\Support\Carbon::create(null, $month)->format('F') . ' (All Years) Statistics')
            : ($month === 0
                ? $year . ' Statistics'
                : \Illuminate\Support\Carbon::create($year, $month, 1)->format('F Y') . ' Statistics');
        $facilityLabels = [2 => 'DEL', 3 => 'GND', 4 => 'TWR', 5 => 'TRC', 6 => 'CTR'];
        $ctrlListJs  = $controllers->map(fn($c) => ['id' => $c->id, 'label' => $c->first_name . ' ' . $c->last_name . ' (' . $c->rating->mapToString() . ')']);
        $ctrlMatch   = $cid ? $controllers->firstWhere('id', $cid) : null;
        $ctrlInitLbl = $ctrlMatch ? ($ctrlMatch->first_name . ' ' . $ctrlMatch->last_name . ' (' . $ctrlMatch->rating->mapToString() . ')') : '';
        $ctrlInitId  = $cid ?? '';
    @endphp
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('controllerPicker', () => ({
                open: false,
                query: @json($ctrlInitLbl),
                selectedId: @json($ctrlInitId),
                controllers: @json($ctrlListJs),
                get filtered() {
                    const sel = this.controllers.find(c => c.id == this.selectedId);
                    if (!this.query || (sel && sel.label === this.query)) return this.controllers;
                    const q = this.query.toLowerCase();
                    return this.controllers.filter(c => c.label.toLowerCase().includes(q));
                },
                choose(c) {
                    this.selectedId = c.id;
                    this.query = c.label;
                    this.open = false;
                },
                clearSelection() {
                    this.selectedId = '';
                    this.open = true;
                },
                onEnter(e) {
                    const first = this.filtered[0];
                    if (first) {
                        this.choose(first);
                        this.$nextTick(() => e.target.form.submit());
                    }
                },
                handleSubmit(e) {
                    if (this.query && !this.selectedId) {
                        const first = this.filtered[0];
                        if (first) { this.selectedId = first.id; this.query = first.label; }
                    }
                    if (!this.query) this.selectedId = '';
                    this.$nextTick(() => e.target.submit());
                },
            }));
        });
    </script>

    {{-- Individual controller view --}}
    @if($selectedController)

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold">
                    {{ $selectedController->name }} ({{ $selectedController->rating->mapToString() }})
                </h2>
                <p class="text-sm text-base-content/60 mt-1">{{ $periodLabel }} &mdash; Stats can take up to 24 hours to update.</p>
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('statistics.index') }}" x-data="controllerPicker" @submit.prevent="handleSubmit($event)">
                <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-end">
                    <div class="flex flex-col gap-1 w-full sm:w-auto">
                        <label class="text-sm">Month</label>
                        <select name="month" class="select w-full sm:w-auto">
                            <option value="all" @selected($month === 0)>All Months</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($m == $month)>
                                    {{ \Illuminate\Support\Carbon::create(null, $m)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 w-full sm:w-auto">
                        <label class="text-sm">Year</label>
                        <select name="year" class="select w-full sm:w-auto">
                            <option value="all" @selected($year === 0)>All Years</option>
                            @foreach($years as $y)
                                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1 w-full sm:w-56 relative" @click.outside="open = false">
                        <label class="text-sm">Controller</label>
                        <input type="hidden" name="cid" :value="selectedId">
                        <div class="relative">
                            <input type="text" x-model="query"
                                @focus="open = true"
                                @input="clearSelection()"
                                @keydown.escape="open = false"
                                @keydown.enter.prevent="onEnter($event)"
                                @keydown.arrow-down.prevent="open = true"
                                placeholder="All Controllers"
                                class="input w-full pr-8">
                            <span class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-base-content/40">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                            <div x-show="open" x-cloak
                                class="absolute z-50 top-full left-0 mt-1 w-full bg-base-200 border border-base-300 rounded-lg shadow-lg">
                                <ul class="max-h-52 overflow-y-auto">
                                    <li>
                                        <button type="button" @click="choose({ id: '', label: '' })"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-base-300 transition-colors"
                                            :class="selectedId === '' ? 'font-semibold' : ''">All Controllers</button>
                                    </li>
                                    <template x-for="c in filtered" :key="c.id">
                                        <li>
                                            <button type="button" @click="choose(c)"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-base-300 transition-colors"
                                                :class="selectedId == c.id ? 'font-semibold' : ''"
                                                x-text="c.label"></button>
                                        </li>
                                    </template>
                                    <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-base-content/50">No results</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto">Search</button>
                </div>
            </form>

            @if($controllerMonthly->isEmpty())
                <p class="text-base">No activity recorded for {{ $selectedController->first_name }}{{ $year === 0 ? '' : ' in ' . $year }}.</p>
            @else
                <x-card-component title="{{ $year === 0 ? 'All-Time' : $year }} Monthly Breakdown">
                    <div class="flex flex-wrap gap-x-8 gap-y-3 border-b border-base-300 pb-4 mb-4 mt-3">
                        @foreach([
                            ['label' => 'Delivery', 'value' => $controllerMonthly->sum('delivery_hours')],
                            ['label' => 'Ground',   'value' => $controllerMonthly->sum('ground_hours')],
                            ['label' => 'Tower',    'value' => $controllerMonthly->sum('tower_hours')],
                            ['label' => 'TRACON',   'value' => $controllerMonthly->sum('approach_hours')],
                            ['label' => 'Center',   'value' => $controllerMonthly->sum('center_hours')],
                            ['label' => 'Total',    'value' => $controllerMonthly->sum(fn($r) => $r->totalHours())],
                        ] as $item)
                            <div>
                                <p class="text-xs text-base-content/60 mb-1">{{ $item['label'] }}</p>
                                <p class="text-lg font-bold">{{ number_format($item['value'], 1) }}h</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm sm:table-md w-full border border-base-300">
                            <thead>
                                <tr>
                                    <th class="whitespace-nowrap">Month</th>
                                    <th class="text-right whitespace-nowrap hidden sm:table-cell">Delivery</th>
                                    <th class="text-right whitespace-nowrap hidden sm:table-cell">Ground</th>
                                    <th class="text-right whitespace-nowrap hidden sm:table-cell">Tower</th>
                                    <th class="text-right whitespace-nowrap hidden sm:table-cell">TRACON</th>
                                    <th class="text-right whitespace-nowrap hidden sm:table-cell">Center</th>
                                    <th class="text-right whitespace-nowrap">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($controllerMonthly as $row)
                                    <tr>
                                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::create($row->year, $row->month, 1)->format($year === 0 ? 'M Y' : 'F') }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $row->delivery_hours > 0 ? number_format($row->delivery_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $row->ground_hours > 0 ? number_format($row->ground_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $row->tower_hours > 0 ? number_format($row->tower_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $row->approach_hours > 0 ? number_format($row->approach_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $row->center_hours > 0 ? number_format($row->center_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right font-bold">{{ number_format($row->totalHours(), 1) }}h</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card-component>

                @if($month !== 0 && $controllerSessions->isNotEmpty())
                    <x-card-component title="{{ \Illuminate\Support\Carbon::create($year, $month, 1)->format('F Y') }} Sessions">
                        <div class="overflow-x-auto mt-3">
                            <table class="table table-zebra table-sm sm:table-md w-full border border-base-300">
                                <thead>
                                    <tr>
                                        <th class="whitespace-nowrap">Position</th>
                                        <th class="whitespace-nowrap">Type</th>
                                        <th class="whitespace-nowrap">Date</th>
                                        <th class="text-right whitespace-nowrap">Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($controllerSessions as $session)
                                        <tr>
                                            <td class="font-mono whitespace-nowrap">{{ $session->callsign }}</td>
                                            <td>{{ $facilityLabels[$session->facility_level] ?? '—' }}</td>
                                            <td class="whitespace-nowrap">{{ $session->start->format('d M Y') }}</td>
                                            <td class="text-right">{{ number_format($session->durationHours(), 2) }}h</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card-component>
                @endif
            @endif
        </div>

    {{-- Leaderboard view --}}
    @else

        <div class="space-y-6">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold">{{ $periodLabel }}</h2>
                <p class="text-base-content/60 mt-1">Stats can take up to 24 hours to update.</p>
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('statistics.index') }}" x-data="controllerPicker" @submit.prevent="handleSubmit($event)">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-end">
                        <div class="flex flex-col gap-1 w-full sm:w-auto">
                            <label class="text-sm">Month</label>
                            <select name="month" class="select w-full sm:w-auto">
                                <option value="all" @selected($month === 0)>All Months</option>
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ $m }}" @selected($m == $month)>
                                        {{ \Illuminate\Support\Carbon::create(null, $m)->format('F') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 w-full sm:w-auto">
                            <label class="text-sm">Year</label>
                            <select name="year" class="select w-full sm:w-auto">
                                <option value="all" @selected($year === 0)>All Years</option>
                                @foreach($years as $y)
                                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 w-full sm:w-56 relative" @click.outside="open = false">
                            <label class="text-sm">Controller</label>
                            <input type="hidden" name="cid" :value="selectedId">
                            <div class="relative">
                                <input type="text" x-model="query"
                                    @focus="open = true"
                                    @input="clearSelection()"
                                    @keydown.escape="open = false"
                                    @keydown.enter.prevent="onEnter($event)"
                                    @keydown.arrow-down.prevent="open = true"
                                    placeholder="All Controllers"
                                    class="input w-full pr-8">
                                <span class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-base-content/40">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </span>
                                <div x-show="open" x-cloak
                                    class="absolute z-50 top-full left-0 mt-1 w-full bg-base-200 border border-base-300 rounded-lg shadow-lg">
                                    <ul class="max-h-52 overflow-y-auto">
                                        <li>
                                            <button type="button" @click="choose({ id: '', label: '' })"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-base-300 transition-colors"
                                                :class="selectedId === '' ? 'font-semibold' : ''">All Controllers</button>
                                        </li>
                                        <template x-for="c in filtered" :key="c.id">
                                            <li>
                                                <button type="button" @click="choose(c)"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-base-300 transition-colors"
                                                    :class="selectedId == c.id ? 'font-semibold' : ''"
                                                    x-text="c.label"></button>
                                            </li>
                                        </template>
                                        <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-base-content/50">No results</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-full sm:w-auto">Search</button>
                    </div>
                </form>

            @if($stats->isEmpty())
                <p class="text-base">No controller activity recorded for this period.</p>
            @else

                {{-- All-Time Hours --}}
                <x-card-component title="All-Time Hours">
                    <div class="mt-3">
                        <p class="text-xs text-base-content/60 mb-1">Total ARTCC Hours{{ $allTimeSince ? ' since ' . $allTimeSince : '' }}</p>
                        <p class="text-3xl font-bold">{{ number_format($allTimeHours, 1) }}h</p>
                    </div>
                </x-card-component>

                {{-- Facility Totals --}}
                <x-card-component title="Facility Totals - {{ $year === 0 ? ($month === 0 ? 'All Time' : \Illuminate\Support\Carbon::create(null, $month)->format('F') . ' (All Years)') : ($month === 0 ? $year : \Illuminate\Support\Carbon::create($year, $month, 1)->format('F Y')) }}">
                    <div class="flex flex-wrap gap-x-8 gap-y-3 mt-3">
                        @foreach([
                            ['label' => 'Delivery', 'value' => $totals['delivery']],
                            ['label' => 'Ground',   'value' => $totals['ground']],
                            ['label' => 'Tower',    'value' => $totals['tower']],
                            ['label' => 'TRACON',   'value' => $totals['approach']],
                            ['label' => 'Center',   'value' => $totals['center']],
                            ['label' => 'Total',    'value' => $totals['total']],
                        ] as $item)
                            <div>
                                <p class="text-xs text-base-content/60 mb-1">{{ $item['label'] }}</p>
                                <p class="text-lg font-bold">{{ number_format($item['value'], 1) }}h</p>
                            </div>
                        @endforeach

                    </div>
                </x-card-component>

                {{-- Top 3 --}}
                @if($stats->count() >= 1)
                    <div>
                        <p class="text-lg font-semibold text-base-content/60 mb-2">Top Controllers</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-base-300 border border-base-300 rounded-lg overflow-hidden">
                            @foreach($stats->take(3) as $idx => $stat)
                                <a href="{{ route('users.show', ['user' => $stat->user->id]) }}"
                                   class="no-underline text-base-content hover:bg-base-200 transition-colors p-6">
                                    <p class="text-xl font-bold leading-snug mb-4">#{{ $idx + 1 }} &mdash; {{ $stat->user->name }} <span class="font-normal text-base-content/60">({{ $stat->user->rating->mapToString() }})</span></p>
                                    <p class="text-3xl font-bold">{{ number_format($stat->totalHours(), 1) }}h</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Full leaderboard table --}}
                <x-card-component title="All Controllers">
                    <div class="overflow-x-auto mt-3">
                        <table class="table table-zebra table-sm sm:table-md w-full border border-base-300">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Controller</th>
                                    <th class="text-right hidden sm:table-cell whitespace-nowrap">Delivery</th>
                                    <th class="text-right hidden sm:table-cell whitespace-nowrap">Ground</th>
                                    <th class="text-right hidden sm:table-cell whitespace-nowrap">Tower</th>
                                    <th class="text-right hidden sm:table-cell whitespace-nowrap">TRACON</th>
                                    <th class="text-right hidden sm:table-cell whitespace-nowrap">Center</th>
                                    <th class="text-right whitespace-nowrap">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats as $idx => $stat)
                                    <tr>
                                        <td class="text-base-content/60 text-sm">{{ $idx + 1 }}</td>
                                        <td>
                                            <a href="{{ route('users.show', ['user' => $stat->user->id]) }}"
                                               class="font-medium hover:underline">{{ $stat->user->name }}</a>
                                            <span class="text-xs text-base-content/60 ml-1">({{ $stat->user->rating->mapToString() }})</span>
                                        </td>
                                        <td class="text-right hidden sm:table-cell">{{ $stat->delivery_hours > 0 ? number_format($stat->delivery_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $stat->ground_hours > 0 ? number_format($stat->ground_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $stat->tower_hours > 0 ? number_format($stat->tower_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $stat->approach_hours > 0 ? number_format($stat->approach_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right hidden sm:table-cell">{{ $stat->center_hours > 0 ? number_format($stat->center_hours, 1).'h' : '—' }}</td>
                                        <td class="text-right font-bold">{{ number_format($stat->totalHours(), 1) }}h</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card-component>

            @endif
        </div>
    @endif
@endsection
