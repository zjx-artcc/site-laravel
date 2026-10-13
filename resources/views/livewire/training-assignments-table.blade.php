<div>
    <div class="flex flex-row gap-x-2 items-center mb-2">
        <label for="showInactive">Show Inactive Requests</label>
        <input
            wire:model="includeInactive"
            wire:click="updateAssignments"
            name="showInactive"
            type="checkbox"
        />
    </div>
    @unless(sizeof($trainingAssignments) == 0)
        <div class='overflow-x-auto'>
        <table class='table table-zebra table-md w-max border-2 border-base-300'>
            <thead>
            <tr>
                <th>CID</th>
                <th>Name</th>
                <th>Instructor</th>
                <th>Training Requested</th>
                <th>Requested At</th>
                <th>Last Session</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($trainingAssignments as $trainingAssignment)
                <tr>
                    <td>
                        <a href="{{route('users.show', ['user' => $trainingAssignment->trainee_id])}}">
                            {{$trainingAssignment->trainee_id}}
                        </a>
                    </td>
                    <td>
                        <a href="{{route('users.show', ['user' => $trainingAssignment->trainee_id])}}">
                            {{$trainingAssignment->trainee->first_name.' '.$trainingAssignment->trainee->last_name}}
                        </a>
                    </td>

                    @if(is_null($trainingAssignment->instructor))
                        <td>None</td>
                    @else
                        <td>
                            <a href="{{route('users.show', ['user' => $trainingAssignment->instructor_id])}}">
                                {{$trainingAssignment->instructor->first_name.' '.$trainingAssignment->instructor->last_name}}
                            </a>
                        </td>
                    @endif

                    <td>{{$trainingAssignment->training_type}}</td>
                    <td>{{(new DateTime($trainingAssignment->created_at))->format("m-d-y, h:m A")}}</td>
                    <td>NOT IMPL. YET</td>

                    @if ($trainingAssignment->active)
                        <td class="text-success">Active</td>
                    @else
                        <td class="text-error">Inactive</td>
                    @endif
                    <td>
                        <div x-data="{
                                open: false,
                                top: 0,
                                left: 0,
                                openMenu() {
                                    const rect = $refs.trigger.getBoundingClientRect();
                                    this.top = rect.bottom + window.scrollY;
                                    this.left = rect.right + window.scrollX - 160;
                                    this.open = true;
                                },
                            }">
                            <div x-ref="trigger" tabindex="0" role="button" class="btn btn-sm" @click="open ? (open = false) : openMenu()">Actions</div>

                            <template x-teleport="body">
                                <ul x-show="open" x-cloak
                                    @click.outside="open = false"
                                    @click="open = false"
                                    :style="`top: ${top}px; left: ${left}px;`"
                                    class="fixed text-base-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-sm border border-base-300">
                                    @haspermission('claim students')
                                        @if (is_null($trainingAssignment->instructor))
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

                                    @if($trainingAssignment->instructor_id == Auth::user()->id)
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

                                    @haspermission('manage students')
                                        <li><a href={{ route('training-assignments.edit', ['assignment' => $trainingAssignment->id]) }}>Edit</a></li>
                                    @endhaspermission
                                </ul>
                            </template>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @else
        <h1>There are no training assignments.</h1>
    @endunless

    <div class="w-full max-w-150 mt-5">
        {{ $trainingAssignments->links() }}
    </div>
</div>
