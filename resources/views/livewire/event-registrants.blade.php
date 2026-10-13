<div>
    @unless (sizeof($registrants) == 0)
        <div class="overflow-x-auto">
            <table class='table table-zebra table-md w-max border-2 border-base-300'>
                <thead>
                <tr class='text-xl font-bold'>
                    <th colspan='2'>Registrants</th>
                    <th colspan='4'>
                        @if(!$publishedPositions)
                            <button wire:click="publishPositions" type="button" class="btn btn-primary">
                                Publish Positions
                            </button>
                        @else
                            <button wire:click="unpublishPositions" type="button" class="btn btn-error">
                                Unpublish Positions
                            </button>
                        @endif

                    </th>
                </tr>
                <tr>
                    <th>Final Position</th>
                    <th>Assigned Time (Zulu)</th>
                    <th>Name</th>
                    <th>Position Requested</th>
                    <th>Requested Time (Zulu)</th>
                    <th>Notes</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($registrants as $registrant)
                    <tr>
                        <td class='border-r-1 border-base-300'>
                            <select name="presetPositions" class="select" wire:model="assignments.{{ $registrant->id }}.assigned_position" @disabled(!$this->canAssign)>
                                <option disabled selected>Select position</option>
                                <option value="">No position</option>
                                @foreach ($positions as $p)
                                    <option value="{{ $p }}">
                                        {{ str_replace('_', ' ', $p) }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class='border-r-1 border-base-300'>
                            <div class="flex flex-col gap-1">
                                <input type="datetime-local" name="assigned_start" wire:model="assignments.{{ $registrant->id }}.assigned_start"
                                       class="input input-bordered input-sm w-full" required @disabled(!$this->canAssign)>
                                <input type="datetime-local" name="assigned_end" wire:model="assignments.{{ $registrant->id }}.assigned_end"
                                       class="input input-bordered input-sm w-full" required @disabled(!$this->canAssign)>
                            </div>
                        </td>
                        <td class='border-r-1 border-base-300'>{{ $registrant->user->first_name }} {{ $registrant->user->last_name }}</td>
                        <td class='border-r-1 border-base-300'>{{ $registrant->requested_position }}</td>
                        <td class='border-r-1 border-base-300'>
                            <div class="flex flex-col text-sm leading-tight">
                                <span>{{ $registrant->start }}</span>
                                <span>{{ $registrant->end }}</span>
                            </div>
                        </td>
                        <td class='border-r-1 border-base-300 max-w-2xs'>
                            @if ($registrant->notes)
                                <span class="block truncate" title="{{ $registrant->notes }}">{{ $registrant->notes }}</span>
                            @else
                                <span class="opacity-50">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->canAssign)
            <button wire:click="saveAll" type="button" class="btn btn-primary mt-4">
                Save All
            </button>
        @endif
    @else
        <h1>There are no registered controllers.</h1>
    @endunless
</div>
