@unless(sizeof($trainingAssignments) == 0)
    <div>
    <table class='table table-zebra table-md border-2 border-base-300 mt-5 w-full'>
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
        @foreach ($trainingAssignments as $trainingAssignment)
            <tr>
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
                    {{  $trainingAssignment->status->label() }}
                </td>
                <td>
                    <ul class='text-accent menu menu-horizontal h-10 items-center gap-x-5 justify-center'>
                        <li>
                            <details>
                                <summary>Actions</summary>
                                <ul class="bg-base-100 text-base-content rounded-t-none p-2 z-10 min-w-max">
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
                            </details>
                        </li>
                    </ul>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
@else
    <h1>There are no training assignments.</h1>
@endunless
