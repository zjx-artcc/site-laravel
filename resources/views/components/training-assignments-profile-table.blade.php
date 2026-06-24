@if(auth()->user()->id == $userId) <!-- Authenicated user is viewing their own profile -->
    @if(is_null($trainingAssignments) || count($trainingAssignments) == 0 || !$trainingAssignments->first()->active) <!-- Authenicated user does not have an active training request -->
        <div>
            <strong>You don't have an active training request. Request training here.</strong>

            <form action="{{route('training-assignment.create')}}"
                    method="POST"
                    class="flex flex-col w-full sm:w-max mt-5"
            >
                @csrf
                <label for="trainingType" class="label">Training Type</label>
                <select name="trainingType" class="select">
                    @foreach(\App\Enums\TrainingType::cases() as $trainingType)
                        <option value="{{$trainingType}}">{{$trainingType->mapToString()}}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary mt-5">Request Training</button>
            </form>
        </div>
    @elseif ($trainingAssignments->first()->instructor_id == null) <!-- Authenicated user has an active training request without an assigned instructor -->
        <div class='p-2'>
            <strong>Your training request is pending assignment to an instructor.</strong>
            <p>Position in queue: {{ \App\Models\TrainingAssignment::where('active', true)
            ->whereNull('instructor_id')
            ->orderBy('created_at')
            ->pluck('id')
            ->search($trainingAssignments->first()->id) + 1
            }}</p>
        
        </div>
    @endif
@endif

<div class="overflow-x-auto">
<table class='table table-zebra table-md mt-5'>
        <thead>
        <tr>
            <th>Instructor</th>
            <th>Training Requested</th>
            <th>Requested At</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
            @unless(sizeof($trainingAssignments) == 0)
                @foreach ($trainingAssignments as $trainingAssignment)
                    <tr>
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
                            <x-training-assignment-status-label :status="$trainingAssignment->status"/>
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" class="text-center">
                        No training assignments found.
                    </td>
                </tr>
            @endunless
        </tbody>
    </table>
</div>

{{ $trainingAssignments->links() }}