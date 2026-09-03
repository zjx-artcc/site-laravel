<div class="navbar sticky top-0 bg-primary text-primary-content z-20 px-3 sm:px-5">
    {{-- Logo + Home --}}
    <div class="flex-1 min-w-0">
        <a href='{{ route('home') }}' class='flex items-center gap-2 shrink-0' title="Home">
            <img src="{{ asset('images/zjx_wide.png') }}" alt="ZJX ARTCC" class="h-8 sm:h-9 w-auto" />
        </a>
    </div>

    {{-- Desktop nav (hidden on mobile) --}}
    <ul class='hidden md:flex menu menu-horizontal items-center gap-x-4'>
        <li><a href="{{ route('home') }}" class="font-medium">Home</a></li>

        <div class="dropdown">
            <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                <span>Events</span>
                <x-dropdown-icon/>
            </div>
            <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-50 w-52 p-2 shadow-sm">
                <li><a href="{{ route('events.index') }}">Upcoming Events</a></li>
                <li><a href="{{ route('staffing-requests.index') }}">Request Staffing</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                <span>Controllers</span>
                <x-dropdown-icon/>
            </div>
            <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-50 w-56 p-2 shadow-sm">
                <li><a href="{{ route('visit.index') }}">Visit vZJX</a></li>
                <li><a href="{{ route('roster.index') }}">Roster</a></li>
                <li><a href="{{ route('staff.index') }}">Facility Staff</a></li>
                <li><a href="{{ route('statistics.index') }}">Statistics</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                <span>Publications</span>
                <x-dropdown-icon/>
            </div>
            <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-50 w-64 p-2 shadow-sm">
                <li><a href="{{ route('publications.index') }}">All Documents</a></li>
                @if($publicationCategories->isNotEmpty())
                    <li class="menu-title text-xs uppercase tracking-wide pt-2">Categories</li>
                    @foreach($publicationCategories as $publicationCategory)
                        <li><a href="{{ route('publications.index') }}#category-{{ $publicationCategory->id }}">{{ $publicationCategory->title }}</a></li>
                    @endforeach
                @endif
            </ul>
        </div>

        <a href="{{ route('feedback.index') }}" role="button" class="m-1">Feedback</a>
        <a href="{{ route('faq.index') }}" role="button" class="m-1">FAQ &amp; Help</a>

        @if(auth()->user()?->hasRole('staff'))
            <a href="{{ route('admin.index') }}" class="m-1 font-medium">Facility Admin</a>
        @endif

        @if(auth()->user())
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="m-1 flex items-center gap-2">
                    <span>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }} - {{ auth()->user()->id }}</span>
                    <x-dropdown-icon/>
                </div>
                <ul tabindex="-1" class="dropdown-content text-base-content menu bg-base-100 rounded-box z-50 w-52 p-2 shadow-sm">
                    <li><a href="{{ route('users.show', [auth()->user()->id]) }}">Profile</a></li>
                    <li><a href="{{ route('auth.logout') }}">Logout</a></li>
                </ul>
            </div>
        @else
            <x-login-button />
        @endif

        <li>
            <label class="swap swap-rotate btn btn-ghost btn-circle text-primary-content" aria-label="Toggle dark mode">
                <input type="checkbox"
                    :checked="theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)"
                    @change="theme = $event.target.checked ? 'dark' : 'light'" />
                <i class="swap-off fa-solid fa-sun text-lg" aria-hidden="true"></i>
                <i class="swap-on fa-solid fa-moon text-lg" aria-hidden="true"></i>
            </label>
        </li>
    </ul>

    {{-- Mobile controls (visible on mobile only) --}}
    <div class="md:hidden flex items-center gap-1" x-data="{ open: false, screen: null }" @keydown.escape.window="open = false">
        {{-- Mobile dark mode toggle --}}
        <label class="swap swap-rotate btn btn-ghost btn-circle btn-sm text-primary-content" aria-label="Toggle dark mode">
            <input type="checkbox"
                :checked="theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)"
                @change="theme = $event.target.checked ? 'dark' : 'light'" />
            <i class="swap-off fa-solid fa-sun text-lg" aria-hidden="true"></i>
            <i class="swap-on fa-solid fa-moon text-lg" aria-hidden="true"></i>
        </label>

        {{-- Mobile hamburger trigger --}}
        <button type="button" class="btn btn-ghost btn-sm px-2 text-primary-content" aria-label="Menu" @click="open = true; screen = null">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>

        {{-- Teleported to <body> so the drawer escapes this navbar's stacking context --}}
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

            {{-- Header: logo, or back button when inside a sub-screen. min-h-16 matches the .navbar behind it --}}
            <div class="flex items-center justify-between gap-2 bg-primary text-primary-content px-3 min-h-16 shrink-0">
                <template x-if="screen === null">
                    <img src="{{ asset('images/zjx_wide.png') }}" alt="ZJX ARTCC" class="h-6 w-auto max-w-full shrink" />
                </template>
                <template x-if="screen !== null">
                    <button type="button" class="flex items-center gap-2 min-w-0" @click="screen = null">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        <span class="font-semibold text-lg">Back</span>
                    </button>
                </template>
                <button type="button" class="btn btn-ghost btn-sm btn-circle text-primary-content shrink-0" @click="open = false" aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="relative flex-1 overflow-hidden">
                {{-- Main menu screen --}}
                <div x-show="screen === null"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                    class="absolute inset-0 overflow-y-auto flex flex-col">
                    <div class="px-3 pt-4 pb-2 shrink-0">
                        <span class="text-xl font-bold text-base-content">Main Menu</span>
                    </div>
                    <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                        <li><a href="{{ route('home') }}" class="rounded-none px-3 py-3.5 text-lg">Home</a></li>

                        <li>
                            <button type="button" class="w-full rounded-none px-3 py-3.5 flex items-center gap-2 text-lg" @click="screen = 'events'">
                                Events
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </li>

                        <li>
                            <button type="button" class="w-full rounded-none px-3 py-3.5 flex items-center gap-2 text-lg" @click="screen = 'controllers'">
                                Controllers
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </li>

                        <li>
                            <button type="button" class="w-full rounded-none px-3 py-3.5 flex items-center gap-2 text-lg" @click="screen = 'publications'">
                                Publications
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </li>

                        <li><a href="{{ route('feedback.index') }}" class="rounded-none px-3 py-3.5 text-lg">Feedback</a></li>
                        <li><a href="{{ route('faq.index') }}" class="rounded-none px-3 py-3.5 text-lg">FAQ &amp; Help</a></li>

                        @if(auth()->user()?->hasRole('staff'))
                            <li><a href="{{ route('admin.index') }}" class="rounded-none px-3 py-3.5 text-lg">Facility Admin</a></li>
                        @endif

                        <div class="divider w-full my-1 mx-0"></div>

                        @if(auth()->user())
                            <li class="px-3 pt-3 pb-1 text-lg font-bold text-base-content">Profile</li>
                            <li><a href="{{ route('users.show', [auth()->user()->id]) }}" class="rounded-none px-3 py-3.5 text-lg">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }} - {{ auth()->user()->rating->mapToString() }}</a></li>
                            <li><a href="{{ route('auth.logout') }}" class="rounded-none px-3 py-3 text-error">Logout</a></li>
                        @else
                            <li><a href="#" @click="open = false" onclick="event.preventDefault(); vatsim_login_modal.showModal()" class="rounded-none px-3 py-3.5 text-lg">Login With VATSIM</a></li>
                        @endif
                    </ul>
                </div>

                {{-- Events sub-screen --}}
                <div x-show="screen === 'events'"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                    class="absolute inset-0 overflow-y-auto flex flex-col">
                    <div class="px-3 pt-4 pb-2 shrink-0">
                        <span class="text-xl font-bold text-base-content">Events</span>
                    </div>
                    <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                        <li><a href="{{ route('events.index') }}" class="rounded-none px-3 py-3.5 text-lg">Upcoming Events</a></li>
                        <li><a href="{{ route('staffing-requests.index') }}" class="rounded-none px-3 py-3.5 text-lg">Request Staffing</a></li>
                    </ul>
                </div>

                {{-- Controllers sub-screen --}}
                <div x-show="screen === 'controllers'"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                    class="absolute inset-0 overflow-y-auto flex flex-col">
                    <div class="px-3 pt-4 pb-2 shrink-0">
                        <span class="text-xl font-bold text-base-content">Controllers</span>
                    </div>
                    <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                        <li><a href="{{ route('visit.index') }}" class="rounded-none px-3 py-3.5 text-lg">Visit vZJX</a></li>
                        <li><a href="{{ route('roster.index') }}" class="rounded-none px-3 py-3.5 text-lg">Roster</a></li>
                        <li><a href="{{ route('staff.index') }}" class="rounded-none px-3 py-3.5 text-lg">Facility Staff</a></li>
                        <li><a href="{{ route('statistics.index') }}" class="rounded-none px-3 py-3.5 text-lg">Statistics</a></li>
                    </ul>
                </div>

                {{-- Publications sub-screen --}}
                <div x-show="screen === 'publications'"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                    class="absolute inset-0 overflow-y-auto flex flex-col">
                    <div class="px-3 pt-4 pb-2 shrink-0">
                        <span class="text-xl font-bold text-base-content">Publications</span>
                    </div>
                    <ul class="menu w-full flex-col flex-nowrap px-0 py-2 gap-0">
                        <li><a href="{{ route('publications.index') }}" class="rounded-none px-3 py-3.5 text-lg">All Documents</a></li>
                        @foreach($mobilePublicationCategories as $publicationCategory)
                            <li><a href="{{ route('publications.index') }}#category-{{ $publicationCategory->id }}" class="rounded-none px-3 py-3.5 text-lg">{{ $publicationCategory->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        </div>
        </template>
    </div>
</div>

{{-- VATSIM login confirmation modal (shared by desktop & mobile triggers) --}}
@guest
<dialog id="vatsim_login_modal" class="modal" x-data="{ confirmed: false }" @close="confirmed = false">
    <div class="modal-box text-base-content">
        <h3 class="font-bold text-lg">Confirm Sign In</h3>
        <p class="py-4 text-sm">
            The information contained on all pages of this website is to be used for flight simulation purposes only on the VATSIM network. It is not intended nor should it be used for real world navigation. This site is not affiliated with the FAA, NATCA, the actual Jacksonville ARTCC, or any governing aviation body. All content contained herein is approved only for use on the VATSIM network.
        </p>
        <label class="flex items-start cursor-pointer gap-3 mt-2">
            <input type="checkbox" x-model="confirmed" class="checkbox checkbox-primary mt-0.5 shrink-0" />
            <span class="text-sm leading-snug">I understand that <strong>we are a virtual organization</strong> and do NOT have any affiliation with the FAA, ZJX, or any government agency.</span>
        </label>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn btn-error">Cancel</button>
            </form>
            <button type="button"
                    x-bind:disabled="!confirmed"
                    x-bind:class="confirmed ? 'btn btn-success' : 'btn btn-success btn-disabled'"
                    @click="window.location.href='{{ route('auth.redirect') }}'">
                Continue with Login
            </button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
@endguest
