@extends('layouts.admin')

@section('title', 'Solo Certs')

@section('body')
    <a href="{{route('solo-certs.create')}}" class="btn btn-primary mb-2">Create a Solo Cert</a>

    <x-search/>

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
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @if(count($soloCerts) == 0)
            <tr>
                <td colspan="5" class="text-xl">No Solo Cert Data to Display</td>
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
                                @if (!$soloCert->expired && !$soloCert->revoked)
                                    @haspermission('revoke solo certs')
                                        <li class="w-max">
                                            <form method="POST" action="{{route('solo-certs.destroy', ['solo_cert' => $soloCert])}}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-full text-left">Revoke Solo Cert</button>
                                            </form>
                                        </li>
                                    @endhaspermission
                                @endif
                            </ul>
                        </template>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
@endsection
