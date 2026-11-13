@unless(sizeof($trainingAssignments) == 0)
    <div class='overflow-x-auto'>
    <table class='table table-md border-2 border-base-300 mt-5 w-full'>
        <thead>
        <tr>
            <th>Student CID</th>
            <th>Student</th>
            <th>Instructor</th>
            <th>Training Requested</th>
            <th>Requested At</th>
            <th>Last Session</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($groups as $group)
            {{-- One heading per training type. The rows are already ordered by
                 course seniority, so the headings come out C1 first, S1 last.
                 base-300 is the most contrasted step against the table in both
                 themes — lighter than base-100 in dark mode, darker in light. --}}
            <tr class="bg-base-300">
                <th colspan="8" class="text-sm font-semibold">
                    @if($group->first()->status->isClosed())
                        Completed / Forfeited
                    @else
                        {{ $group->first()->training_type->mapToString() }}
                    @endif
                    <span class="font-normal text-base-content/60">({{ $group->count() }})</span>
                </th>
            </tr>

            {{-- Striping is done here rather than with daisyUI's `table-zebra`
                 for two reasons: table-zebra alternates on nth-child across the
                 whole tbody, so the heading rows throw its rhythm off and a
                 group's first row is striped or not depending on how many rows
                 preceded it; and it gives no way to skip a group. Striping
                 restarts per group, and a group with a single row is left
                 unstriped so one lone student does not read as a highlight. --}}
            @foreach ($group as $trainingAssignment)
            <tr @class(['bg-base-200' => $group->count() > 1 && $loop->even])>
                <td>
                    <a href="{{route('users.show', ['user' => $trainingAssignment->user_id])}}">
                        {{$trainingAssignment->student->id}}
                    </a>
                </td>
                <td>
                    <a href="{{route('users.show', ['user' => $trainingAssignment->user_id])}}">
                        {{$trainingAssignment->student->name}}
                    </a>
                </td>

                @if(is_null($trainingAssignment->instructor))
                    <td>None</td>
                @else
                    <td>
                        <a href="{{route('users.show', ['user' => $trainingAssignment->instructor_id])}}">
                            {{$trainingAssignment->instructor->name}}
                        </a>
                    </td>
                @endif

                <td>{{$trainingAssignment->training_type->mapToString()}}</td>
                <td>{{(new DateTime($trainingAssignment->created_at))->format("m-d-y, h:m A")}}</td>
                <td>
                    @if(count($trainingAssignment->student->trainingTicketsAsStudent) == 0)
                        None Logged
                    @else
                        <a href="{{route('training-tickets.show', ['ticket' => $trainingAssignment->student->trainingTicketsAsStudent->first()])}}">
                            {{(new DateTime($trainingAssignment->student->trainingTicketsAsStudent->first()->session_start))->format("m-d-y, h:m A")}}
                        </a>
                    @endif
                </td>

                <td>
                    {{-- Editable in place for instructors; a plain badge for everyone else. --}}
                    @if($canManageStudents)
                        @livewire('training-assignment-status', ['assignment' => $trainingAssignment], key($trainingAssignment->id))
                    @else
                        <x-training-assignment-status-label :status="$trainingAssignment->status"/>
                    @endif

                    {{-- An open status with `active = false` is a row parked
                         under the Edit page's old Active checkbox. Nothing
                         creates these any more, but the ones already in the
                         database still refuse claim() and drop(), so they say
                         so rather than showing a green badge and a Claim action
                         that errors. --}}
                    @if(! $trainingAssignment->active && ! $trainingAssignment->status->isClosed())
                        <span class="badge badge-ghost badge-sm whitespace-nowrap" title="Parked: cannot be claimed or dropped until its status is set again">Inactive</span>
                    @endif
                </td>
                <td>
                    <div x-data="{
                            open: false,
                            top: 0,
                            left: 0,
                            openMenu() {
                                // getBoundingClientRect() is already viewport-relative,
                                // which is the coordinate space `fixed` uses — adding a
                                // scroll offset here would push the menu down the page
                                // by the scroll distance. The 160 right-aligns the
                                // w-40 menu with the trigger's right edge.
                                const rect = $refs.trigger.getBoundingClientRect();
                                this.top = rect.bottom + 4;
                                this.left = rect.right - 160;
                                this.open = true;
                            },
                        }">
                        <div x-ref="trigger" tabindex="0" role="button" class="btn btn-sm" @click="open ? (open = false) : openMenu()">Actions</div>

                        <template x-teleport="body">
                            {{-- Closed on scroll/resize rather than re-measured: the
                                 coordinates are a snapshot, so a moving page would
                                 leave the menu stranded beside nothing. --}}
                            <ul x-show="open" x-cloak
                                @click.outside="open = false"
                                @click="open = false"
                                @scroll.window="open = false"
                                @resize.window="open = false"
                                :style="`top: ${top}px; left: ${left}px;`"
                                class="fixed text-base-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-sm border border-base-300 min-w-max">
                                @haspermission('claim students')
                                {{-- claim() and drop() both refuse an inactive
                                     assignment, so a parked row offers neither
                                     instead of erroring on the attempt. --}}
                                @if (is_null($trainingAssignment->instructor) && $trainingAssignment->active)
                                    <li>
                                        <form
                                            action="{{route("training-assignments.claim", ["assignment" => $trainingAssignment->id])}}"
                                            method="POST"
                                        >
                                            @method('PUT')
                                            @csrf
                                            <button type="submit">Claim Student</button>
                                        </form>
                                    </li>
                                @endif
                                @endhaspermission

                                @if($trainingAssignment->instructor_id == Auth::user()->id && $trainingAssignment->active)
                                    <li>
                                        <form
                                            action="{{route("training-assignments.drop", ["assignment" => $trainingAssignment->id])}}"
                                            method="POST"
                                        >
                                            @method('PUT')
                                            @csrf
                                            <button type="submit">Drop Student</button>
                                        </form>
                                    </li>
                                @endif

                                @if($canManageStudents)
                                    <li><a href={{ route('training-assignments.edit', ['assignment' => $trainingAssignment->id]) }}>Edit</a></li>
                                @endif
                            </ul>
                        </template>
                    </div>
                </td>
            </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
    </div>
@else
    <h1>There are no training assignments.</h1>
@endunless
