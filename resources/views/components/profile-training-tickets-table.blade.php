{{-- Desktop table (hidden on mobile) --}}
<div class="hidden sm:block overflow-x-auto">
<table class="table table-zebra">
    <thead>
    <tr>
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
            <td colspan="6" class="text-center">No Training Data to Display</td>
        </tr>
    @endif
    @foreach($trainingTickets as $trainingTicket)
        <tr>
            <td>
                <a href="{{route('users.show', ['user' => $trainingTicket->instructor->id])}}">
                    {{$trainingTicket->instructor->first_name.' '.$trainingTicket->instructor->last_name}}
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

{{-- Mobile cards (visible only on small screens) --}}
<div class="sm:hidden space-y-2">
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
                <div class="flex items-center justify-between text-xs text-base-content/60">
                    <span>{{$trainingTicket->instructor->first_name}} {{$trainingTicket->instructor->last_name}}</span>
                    <span>{{$trainingTicket->session_start}}</span>
                </div>
                <x-rating-readonly :rating="$trainingTicket->score"/>
            </div>
        </a>
    @empty
        <div class="text-center py-8 text-base-content/50">
            <p class="text-sm">No Training Data to Display</p>
        </div>
    @endforelse
</div>
