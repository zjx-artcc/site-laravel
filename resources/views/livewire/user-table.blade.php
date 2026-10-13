<div x-data="{ search: @entangle('search').live }">
    <x-search/>

    @unless(sizeof($users) == 0)
        <div class='overflow-x-auto'>
        <table class='table table-zebra table-md w-max border-2 rounded-md border-base-300 mt-5'>
            <thead>
                <tr class='text-xl font-bold'>
                    <th>ZJX Roster</th>
                </tr>
                <tr>
                    <th>Name (CIDs)</th>
                    <th>OIs</th>
                    <th>Rating</th>
                    @haspermission('manage users')
                        <th>Email</th>
                        <th>Joined</th>
                        <th>Last Activity</th>
                    @endhaspermission
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class='border-r-1 border-base-300'>
                            <a href={{ route('users.show', ['user' => $user->id]) }} class='text-base-content no-underline'>
                                {{ $user->nameReversed }}  ({{ $user->id }})
                                
                                @unless(is_null($user->operating_initials) || strlen($user->operating_initials) == 0)
                                    ({{ $user->operating_initials }})
                                @endunless
                            </a>

                            @unless(strcasecmp($user->facility, env('VATUSA_FACILITY')) == 0)
                                <h3 class='badge badge-info badge-sm ml-2'>{{ $user->facility }} Visitor</h3>
                            @endunless
                        </td>
                        <td class='border-r-1 border-base-300' @class(['bg-red-200' => is_null($user->operating_initials) || strlen($user->operating_initials) == 0])>
                            @if(!is_null($user->operating_initials) && strlen($user->operating_initials) > 0)
                                {{ $user->operating_initials }}
                            @else
                                Unassigned
                            @endif
                        </td>
                        <td class='border-r-1 border-base-300'>{{ $user->rating->mapToString() }}</td>
                        
                        @haspermission('manage users')
                            <td class='border-r-1 border-base-300'>{{ $user->email }}</td>
                            <td class='border-r-1 border-base-300'>{{ $user->joined_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class='border-r-1 border-base-300'>{{ $user->updated_at?->format('Y-m-d') ?? '—' }}</td>
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
                                            <li><a href={{ route('users.edit', ['user' => $user->id]) }}>Edit</a></li>
                                        </ul>
                                    </template>
                                </div>
                            </td>
                        @endhaspermission
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @else
        <h1>There are no rostered users.</h1>
    @endunless

    <div class="w-full max-w-150 mt-5">
        {{ $users->links() }}
    </div>
</div>
