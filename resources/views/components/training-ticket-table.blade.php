{{-- Desktop table (hidden on mobile) --}}
<div class="hidden sm:block overflow-x-auto">
<table class="table table-zebra mt-2">
    <thead>
    <tr>
        <th>Student</th>
        <th>Instructor</th>
        <th>Position</th>
        <th>Progress Rating</th>
        <th>Session Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    @if(count($trainingTickets) == 0)
        <tr>
            <td colspan="7" class="text-center">
                No training tickets found.
            </td>
        </tr>
    @endif

    @foreach($trainingTickets as $trainingTicket)
        <tr>
            <td>
                <a href="{{route('users.show', ['user' => $trainingTicket->student->id])}}">
                    {{$trainingTicket->student->name}}
                </a>
            </td>
            <td>
                <a href="{{route('users.show', ['user' => $trainingTicket->instructor->id])}}">
                    {{$trainingTicket->instructor->name}}
                </a>
            </td>
            <td>{{$trainingTicket->position}}</td>
            <td>
                <x-rating-readonly :rating="$trainingTicket->score"/>
            </td>
            <td>{{$trainingTicket->session_start}}</td>
            <td>
                @if ($trainingTicket->vatusa_synced)
                    <span class="badge badge-success badge-sm">VATUSA Synced</span>
                @else
                    <span class="badge badge-warning badge-sm">Pending Sync</span>
                @endif
            </td>
            <td>
                <a href="{{route('training-tickets.show', ['ticket' => $trainingTicket->id])}}" class="link link-primary text-sm">
                    View
                </a>
            </td>
        </tr>
    @endforeach

    </tbody>
</table>
</div>

{{-- Mobile cards --}}
<div class="sm:hidden space-y-2 mt-2">
    @forelse($trainingTickets as $trainingTicket)
        <a href="{{route('training-tickets.show', ['ticket' => $trainingTicket->id])}}"
           class="card card-compact bg-base-200/50 border border-base-200 hover:bg-base-200 transition active:scale-[0.98]">
            <div class="card-body p-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-sm">{{$trainingTicket->position}}</span>
                    @if ($trainingTicket->vatusa_synced)
                        <span class="badge badge-success badge-xs">Synced</span>
                    @else
                        <span class="badge badge-warning badge-xs">Pending</span>
                    @endif
                </div>
                <div class="text-xs text-base-content/60 space-y-0.5">
                    <div class="flex justify-between">
                        <span>Student: {{$trainingTicket->student->name}}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Instructor: {{$trainingTicket->instructor->name}}</span>
                        <span>{{$trainingTicket->session_start}}</span>
                    </div>
                </div>
                <x-rating-readonly :rating="$trainingTicket->score"/>
            </div>
        </a>
    @empty
        <div class="text-center py-8 text-base-content/50">
            <p class="text-sm">No training tickets found.</p>
        </div>
    @endforelse
</div>
