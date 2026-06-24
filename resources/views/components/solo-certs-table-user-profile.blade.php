<div class="overflow-x-auto">
<table class="table table-zebra mt-2">
        <thead>
        <tr>
            <th>User</th>
            <th>Issued By</th>
            <th>Position</th>
            <th>Created At</th>
            <th>Expires On</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @if(count($soloCerts) == 0)
            <tr>
                    <td colspan="5" class="text-center">
                        No solo certifications found.
                    </td>
                </tr>
        @endif

        @foreach($soloCerts as $soloCert)
            <tr>
                <td>
                    <a href="{{route('users.show', ['user' => $soloCert->user])}}">
                        {{$soloCert->user->nameReversed}} ({{$soloCert->user->id}})
                    </a>
                </td>
                <td>
                    <a href="{{route('users.show', ['user' => $soloCert->issuedBy])}}">
                        {{$soloCert->issuedBy->nameReversed}} ({{$soloCert->issuedBy->id}})
                    </a>
                </td>
                <td>{{$soloCert->position}}</td>
                <td>{{$soloCert->created_at->format('Y-m-d')}}</td>
                <td>{{$soloCert->expires->format('Y-m-d')}}</td>
                <td>
                    @if ($soloCert->revoked)
                        <div class="badge badge-error text-error-content">Revoked</div>
                    @elseif($soloCert->expired)
                        <div class="badge badge-warning text-error-content">Expired</div>
                    @else
                        <div class="badge badge-success text-success-content">Active</div>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>