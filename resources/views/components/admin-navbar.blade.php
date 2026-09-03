<div class="navbar bg-info text-white z-10">
    <div class="flex-1 ml-5">
        <a href='{{ route('admin.index') }}' class='text-xl'>Admin Actions</a>
    </div>

    {{-- Desktop nav (hidden on mobile) --}}
    <ul class='hidden md:flex menu menu-horizontal items-center gap-x-5 justify-center'>
        @if(auth()->user()?->can('manage faqs'))
            <div class="">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <a href="{{ route('admin.faqs.index') }}">FAQs</a>
                </div>
            </div>
        @endif

        @if(auth()->user()?->hasRole('training'))
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Training Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('training-tickets.index') }}>Training Tickets</a></li>
                    <li><a href={{ route('training-assignments.index') }}>Training Assignments</a></li>
                    <li><a href={{ route('solo-certs.index') }}>Solo Certs</a></li>
                    @if(auth()->user()?->hasPermissionTo('certifications:write'))
                        <li><a href={{ route('certifications.index') }}>Certifications</a></li>
                    @endif
                </ul>
            </div>
        @endif

        @if(auth()->user()?->hasRole('facilities'))
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Data Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    @if(auth()->user()?->hasPermissionTo('manage statistics prefixes'))
                    <li><a href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a></li>
                    @endif

                    @if(auth()->user()?->hasPermissionTo('certification-facilities:write'))
                        <li><a href={{ route('certification-facilities.index') }}>Certification Facilities</a></li>
                    @endif
                    <li><a href="{{ route('admin.publications.index') }}">Document Management</a></li>
                </ul>
            </div>
        @endif

        @if(auth()->user()?->hasRole('events'))
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Events Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('admin.events.index') }}>Events</a></li>
                    <li><a href="{{ route('admin.events.position-presets.index') }}">Position Presets</a></li>
                    <li><a href="{{ route('admin.events.event-fields.index') }}">Event Field Presets</a></li>
                    @if(auth()->user()?->hasPermissionTo('staffing-requests:read'))
                        <li>
                            <a href={{ route('admin.staffing-requests.index') }}>Staffing Requests
                                @if($openStaffingRequests > 0)
                                    <span class='badge badge-primary'>{{ $openStaffingRequests }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        @endif

        @if(auth()->user()?->hasRole('admin'))
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>Facility Admin</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-1 w-52 p-2 shadow-sm">
                    <li><a href={{ route('admin.index') }}>Dashboard</a></li>
                    <li><a href={{ route('manage-users.index') }}>User Management</a></li>
                    <li>
                        <a href={{ route('visit.manage') }}>Visitor Requests
                            @if($pendingVisitorRequests > 0)
                                <span class='badge badge-primary'>{{ $pendingVisitorRequests }}</span>
                            @endif
                        </a>
                    </li>
                    <li>
                        <a href={{ route('loa.manage') }}>LOA Requests
                            @if($pendingLoas > 0)
                                <span class='badge badge-primary'>{{ $pendingLoas }}</span>
                            @endif
                        </a>
                    </li>
                    <li><a href="{{ route('admin.news.index') }}">News Management</a></li>
                    <li><a href={{ route('logs.index') }}>Audit Log</a></li>
                </ul>
            </div>
        @endif
    </ul>

    {{-- Mobile hamburger (visible on mobile only) --}}
    <div class="md:hidden mr-3" x-data="{ open: false, screen: null }" @keydown.escape.window="open = false">
        <button type="button" class="btn btn-ghost btn-sm px-2" aria-label="Admin Menu" @click="open = true; screen = null">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>

        {{-- Teleported to <body> so the drawer escapes this navbar's z-10 stacking context --}}
        <template x-teleport="body">
        <div>
        {{-- Backdrop --}}
        <div x-show="open" x-cloak @click="open = false"
            x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 z-40"></div>

        {{-- Tall slide-out drawer (partial width) --}}
        <div x-show="open" x-cloak
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 z-50 w-56 max-w-[75vw] bg-base-300 text-base-content shadow-xl flex flex-col">

            {{-- Header: title, or back button when inside a sub-screen. min-h-16 matches the .navbar behind it --}}
            <div class="flex items-center justify-between gap-2 bg-info text-white px-3 min-h-16 shrink-0">
                <template x-if="screen === null">
                    <span class="text-xl font-bold">Admin Menu</span>
                </template>
                <template x-if="screen !== null">
                    <button type="button" class="flex items-center gap-2" @click="screen = null">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        <span class="font-semibold text-lg">Back</span>
                    </button>
                </template>
                <button type="button" class="btn btn-ghost btn-sm btn-circle shrink-0" @click="open = false" aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="relative flex-1 overflow-hidden">
                {{-- Main menu screen --}}
                <div x-show="screen === null"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                    class="absolute inset-0 overflow-y-auto flex flex-col">
                    <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                        @if(auth()->user()?->can('manage faqs'))
                            <li><a href="{{ route('admin.faqs.index') }}" class="rounded-none px-3 py-3.5 text-lg">FAQs</a></li>
                        @endif

                        @if(auth()->user()?->hasRole('training'))
                            <li>
                                <button type="button" class="w-full rounded-none px-3 py-3.5 flex items-center gap-2 text-lg" @click="screen = 'training-admin'">
                                    Training Admin
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </li>
                        @endif

                        @if(auth()->user()?->hasRole('facilities'))
                            <li>
                                <button type="button" class="w-full rounded-none px-3 py-3.5 flex items-center gap-2 text-lg" @click="screen = 'data-admin'">
                                    Data Admin
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </li>
                        @endif

                        @if(auth()->user()?->hasRole('events'))
                            <li>
                                <button type="button" class="w-full rounded-none px-3 py-3.5 flex items-center gap-2 text-lg" @click="screen = 'events-admin'">
                                    Events Admin
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </li>
                        @endif

                        @if(auth()->user()?->hasRole('admin'))
                            <li>
                                <button type="button" class="w-full rounded-none px-3 py-3.5 flex items-center gap-2 text-lg" @click="screen = 'facility-admin'">
                                    Facility Admin
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- Training Admin sub-screen --}}
                @if(auth()->user()?->hasRole('training'))
                    <div x-show="screen === 'training-admin'"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                        class="absolute inset-0 overflow-y-auto flex flex-col">
                        <div class="px-3 pt-4 pb-2 shrink-0">
                            <span class="text-xl font-bold text-info">Training Admin</span>
                        </div>
                        <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                            <li><a href={{ route('training-tickets.index') }} class="rounded-none px-3 py-3.5 text-lg">Training Tickets</a></li>
                            <li><a href={{ route('training-assignments.index') }} class="rounded-none px-3 py-3.5 text-lg">Training Assignments</a></li>
                            <li><a href={{ route('solo-certs.index') }} class="rounded-none px-3 py-3.5 text-lg">Solo Certs</a></li>
                            @if(auth()->user()?->can('certifications:write'))
                                <li><a href={{ route('certifications.index') }} class="rounded-none px-3 py-3.5 text-lg">Certifications</a></li>
                            @endif
                        </ul>
                    </div>
                @endif

                {{-- Data Admin sub-screen --}}
                @if(auth()->user()?->hasRole('facilities'))
                    <div x-show="screen === 'data-admin'"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                        class="absolute inset-0 overflow-y-auto flex flex-col">
                        <div class="px-3 pt-4 pb-2 shrink-0">
                            <span class="text-xl font-bold text-info">Data Admin</span>
                        </div>
                        <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                            @if(auth()->user()?->can('manage statistics prefixes'))
                                <li><a href="{{ route('statistics-prefixes.index') }}" class="rounded-none px-3 py-3.5 text-lg">Statistics Prefixes</a></li>
                            @endif
                            @if(auth()->user()?->can('certification-facilities:write'))
                                <li><a href={{ route('certification-facilities.index') }} class="rounded-none px-3 py-3.5 text-lg">Certification Facilities</a></li>
                            @endif
                            <li><a href="{{ route('admin.publications.index') }}" class="rounded-none px-3 py-3.5 text-lg">Document Management</a></li>
                        </ul>
                    </div>
                @endif

                {{-- Events Admin sub-screen --}}
                @if(auth()->user()?->hasRole('events'))
                    <div x-show="screen === 'events-admin'"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                        class="absolute inset-0 overflow-y-auto flex flex-col">
                        <div class="px-3 pt-4 pb-2 shrink-0">
                            <span class="text-xl font-bold text-info">Events Admin</span>
                        </div>
                        <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                            <li><a href={{ route('admin.events.index') }} class="rounded-none px-3 py-3.5 text-lg">Events</a></li>
                            <li><a href="{{ route('admin.events.position-presets.index') }}" class="rounded-none px-3 py-3.5 text-lg">Position Presets</a></li>
                            <li><a href="{{ route('admin.events.event-fields.index') }}" class="rounded-none px-3 py-3.5 text-lg">Event Field Presets</a></li>
                            @if(auth()->user()?->can('staffing-requests:read'))
                                <li>
                                    <a href={{ route('admin.staffing-requests.index') }} class="rounded-none px-3 py-3.5 text-lg">Staffing Requests
                                        @if($openStaffingRequests > 0)
                                            <span class='badge badge-primary'>{{ $openStaffingRequests }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif

                {{-- Facility Admin sub-screen --}}
                @if(auth()->user()?->hasRole('admin'))
                    <div x-show="screen === 'facility-admin'"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                        class="absolute inset-0 overflow-y-auto flex flex-col">
                        <div class="px-3 pt-4 pb-2 shrink-0">
                            <span class="text-xl font-bold text-info">Facility Admin</span>
                        </div>
                        <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                            <li><a href={{ route('admin.index') }} class="rounded-none px-3 py-3.5 text-lg">Dashboard</a></li>
                            <li><a href={{ route('manage-users.index') }} class="rounded-none px-3 py-3.5 text-lg">User Management</a></li>
                            <li>
                                <a href={{ route('visit.manage') }} class="rounded-none px-3 py-3.5 text-lg">Visitor Requests
                                    @if($pendingVisitorRequests > 0)
                                        <span class='badge badge-primary'>{{ $pendingVisitorRequests }}</span>
                                    @endif
                                </a>
                            </li>
                            <li>
                                <a href={{ route('loa.manage') }} class="rounded-none px-3 py-3.5 text-lg">LOA Requests
                                    @if($pendingLoas > 0)
                                        <span class='badge badge-primary'>{{ $pendingLoas }}</span>
                                    @endif
                                </a>
                            </li>
                            <li><a href={{ route('admin.news.index') }} class="rounded-none px-3 py-3.5 text-lg">News Management</a></li>
                            <li><a href={{ route('logs.index') }} class="rounded-none px-3 py-3.5 text-lg">Audit Log</a></li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
        </div>
        </template>
    </div>
</div>
