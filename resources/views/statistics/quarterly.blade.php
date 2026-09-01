@extends('layouts.admin')

@section('title', 'Roster Purge Assistant')

@section('body')

    @php
        $quarterMonths = [
            1 => 'Jan – Mar',
            2 => 'Apr – Jun',
            3 => 'Jul – Sep',
            4 => 'Oct – Dec',
        ];
        $periodLabel = "Q{$quarter} {$year} ({$quarterMonths[$quarter]})";
        $homeFacility = config('app.vatusa_facility');
        $statusOf = function ($user) use ($homeFacility) {
            if (!$user->rostered) {
                return ['label' => 'Not Rostered', 'badge' => null];
            }
            if (strcasecmp($user->facility, $homeFacility) === 0) {
                return ['label' => 'Home', 'badge' => 'badge-accent'];
            }
            return ['label' => "Visitor ({$user->facility})", 'badge' => 'badge-error'];
        };
        $ctrlListJs  = $controllers->map(fn($c) => ['id' => $c->id, 'label' => $c->name . ' (' . $c->rating->mapToString() . ')']);
        $ctrlMatch   = $cid ? $controllers->firstWhere('id', $cid) : null;
        $ctrlInitLbl = $ctrlMatch ? ($ctrlMatch->name . ' (' . $ctrlMatch->rating->mapToString() . ')') : '';
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

    <div class="space-y-6">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold">Roster Purge Assistant</h2>
            <p class="text-base-content/60 mt-1">{{ $periodLabel }} &mdash; controllers below the threshold are flagged below.</p>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('statistics.quarterly') }}" x-data="controllerPicker" @submit.prevent="handleSubmit($event)">
            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-end">
                <div class="flex flex-col gap-1 w-full sm:w-auto">
                    <label class="text-sm">Quarter</label>
                    <select name="quarter" class="select w-full sm:w-auto">
                        @foreach($quarterMonths as $q => $label)
                            <option value="{{ $q }}" @selected($q == $quarter)>Q{{ $q }} ({{ $label }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1 w-full sm:w-auto">
                    <label class="text-sm">Year</label>
                    <select name="year" class="select w-full sm:w-auto">
                        @foreach($years as $y)
                            <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1 w-full sm:w-40">
                    <label class="text-sm">Flag below (hours)</label>
                    <input type="number" name="threshold" step="0.1" min="0" value="{{ $threshold }}" class="input w-full">
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
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <label class="label cursor-pointer gap-2" title="Limits the All Controllers table below to currently rostered controllers. The Flagged Controllers table always excludes unrostered controllers.">
                        <input type="checkbox" name="rostered_only" value="1" class="checkbox" @checked($rosteredOnly)>
                        <span class="text-sm">Rostered only (All Controllers table)</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-full sm:w-auto">Search</button>
                <a href="{{ route('statistics.quarterly.export', request()->query()) }}" class="btn btn-outline w-full sm:w-auto">Export Flagged CSV</a>
            </div>
        </form>

        {{-- Flagged controllers --}}
        <x-card-component title="Flagged Controllers ({{ $flagged->total() }})">
            @if($flagged->total() === 0)
                <p class="text-base mt-3">No controllers are below {{ number_format($threshold, 1) }}h for {{ $periodLabel }}.</p>
            @else
                @haspermission('remove inactive controllers')
                    <form method="POST" action="{{ route('statistics.quarterly.remove') }}"
                          x-data="{ selected: [], reason: '' }"
                          @submit="if (selected.length === 0) { $event.preventDefault(); return; }
                                   if (!reason.trim()) { $event.preventDefault(); alert('A removal reason is required.'); return; }
                                   if (!confirm('Remove ' + selected.length + ' controller(s) from the roster? This cannot be undone.')) { $event.preventDefault(); }">
                        @csrf
                @endhaspermission
                <div class="overflow-x-auto mt-3">
                    <table class="table table-zebra table-sm sm:table-md w-full border border-base-300">
                        <thead>
                            <tr>
                                @haspermission('remove inactive controllers')
                                    <th class="w-8">
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               @click="selected = $event.target.checked ? [{{ $flagged->getCollection()->map(fn($r) => $r->user->id)->implode(',') }}] : []"
                                               :checked="selected.length === {{ $flagged->count() }} && selected.length > 0">
                                    </th>
                                @endhaspermission
                                <th>Controller</th>
                                <th class="hidden sm:table-cell">Status</th>
                                <th class="text-right whitespace-nowrap">Training</th>
                                <th class="text-right whitespace-nowrap">{{ $periodLabel }} Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($flagged as $row)
                                <tr>
                                    @haspermission('remove inactive controllers')
                                        <td>
                                            <input type="checkbox" name="user_ids[]" value="{{ $row->user->id }}"
                                                   class="checkbox checkbox-sm" x-model.number="selected">
                                        </td>
                                    @endhaspermission
                                    <td>
                                        <a href="{{ route('users.show', ['user' => $row->user->id]) }}"
                                           class="font-medium hover:underline">{{ $row->user->name }}</a>
                                        <span class="text-xs text-base-content/60 ml-1">({{ $row->user->rating->mapToString() }})</span>
                                    </td>
                                    @php($status = $statusOf($row->user))
                                    <td class="hidden sm:table-cell">
                                        @if($status['badge'])
                                            <span class="badge {{ $status['badge'] }}">{{ $status['label'] }}</span>
                                        @else
                                            <span class="text-sm">{{ $status['label'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <span class="font-medium">{{ number_format($row->training_total, 1) }}h</span>
                                        <span class="text-xs text-base-content/60 ml-1">(S {{ number_format($row->training_student, 1) }} / I {{ number_format($row->training_instructor, 1) }})</span>
                                    </td>
                                    <td class="text-right font-bold text-error">{{ number_format($row->total, 1) }}h</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @haspermission('remove inactive controllers')
                        <div class="flex flex-col sm:flex-row sm:items-end gap-3 mt-4">
                            <div class="flex flex-col gap-1 w-full sm:max-w-md">
                                <label class="text-sm">Removal reason (applied to all selected)</label>
                                <input type="text" name="reason" x-model="reason" maxlength="255" placeholder="e.g. Inactivity — below quarterly hours minimum" class="input w-full">
                            </div>
                            <button type="submit" class="btn btn-error w-full sm:w-auto"
                                    :disabled="selected.length === 0">
                                Remove Selected (<span x-text="selected.length"></span>)
                            </button>
                        </div>
                        <p class="text-xs text-base-content/60 mt-2">Removes the selected controllers from the VATUSA roster. Only controllers on the current page can be selected at once.</p>
                    </form>
                @endhaspermission
                <div class="mt-3">{{ $flagged->links() }}</div>
            @endif
        </x-card-component>

        {{-- Full breakdown --}}
        <x-card-component title="All Controllers with Logged History ({{ $rows->total() }}) — {{ $periodLabel }}">
            <p class="text-sm text-base-content/60 -mt-1 mb-2">
                Everyone who has ever logged StatsSim hours, not just the current roster. 0.0h means no activity in {{ $periodLabel }} specifically &mdash; check Status for their current roster standing. Click a controller's name to see their most recent hours.
            </p>
            <div class="overflow-x-auto mt-3">
                <table class="table table-zebra table-sm sm:table-md w-full border border-base-300">
                    <thead>
                        <tr>
                            <th>Controller</th>
                            <th class="hidden sm:table-cell">Status</th>
                            <th class="text-right hidden sm:table-cell whitespace-nowrap">Delivery</th>
                            <th class="text-right hidden sm:table-cell whitespace-nowrap">Ground</th>
                            <th class="text-right hidden sm:table-cell whitespace-nowrap">Tower</th>
                            <th class="text-right hidden sm:table-cell whitespace-nowrap">TRACON</th>
                            <th class="text-right hidden sm:table-cell whitespace-nowrap">Center</th>
                            <th class="text-right hidden lg:table-cell">Training (Student)</th>
                            <th class="text-right hidden lg:table-cell">Training (Instructor)</th>
                            <th class="text-right whitespace-nowrap">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('users.show', ['user' => $row->user->id]) }}"
                                       class="font-medium hover:underline">{{ $row->user->name }}</a>
                                    <span class="text-xs text-base-content/60 ml-1">({{ $row->user->rating->mapToString() }})</span>
                                </td>
                                @php($status = $statusOf($row->user))
                                <td class="hidden sm:table-cell">
                                    @if($status['badge'])
                                        <span class="badge {{ $status['badge'] }}">{{ $status['label'] }}</span>
                                    @else
                                        <span class="text-sm">{{ $status['label'] }}</span>
                                    @endif
                                </td>
                                <td class="text-right hidden sm:table-cell">{{ $row->delivery > 0 ? number_format($row->delivery, 1).'h' : '—' }}</td>
                                <td class="text-right hidden sm:table-cell">{{ $row->ground > 0 ? number_format($row->ground, 1).'h' : '—' }}</td>
                                <td class="text-right hidden sm:table-cell">{{ $row->tower > 0 ? number_format($row->tower, 1).'h' : '—' }}</td>
                                <td class="text-right hidden sm:table-cell">{{ $row->approach > 0 ? number_format($row->approach, 1).'h' : '—' }}</td>
                                <td class="text-right hidden sm:table-cell">{{ $row->center > 0 ? number_format($row->center, 1).'h' : '—' }}</td>
                                <td class="text-right hidden lg:table-cell">{{ number_format($row->training_student, 1) }}h</td>
                                <td class="text-right hidden lg:table-cell">{{ number_format($row->training_instructor, 1) }}h</td>
                                <td class="text-right font-bold {{ $row->total < $threshold ? 'text-error' : '' }}">{{ number_format($row->total, 1) }}h</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $rows->links() }}</div>
        </x-card-component>
    </div>

@endsection
