<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{
          theme: localStorage.getItem('theme'),
          sidebarOpen: localStorage.getItem('sidebarOpen') === 'true',
          mobileOpen: false,
          isDesktop: window.matchMedia('(min-width: 1024px)').matches
      }"
      x-init="
          $watch('theme', value => value ? localStorage.setItem('theme', value) : localStorage.removeItem('theme'));
          $watch('sidebarOpen', value => localStorage.setItem('sidebarOpen', value));
          window.matchMedia('(min-width: 1024px)').addEventListener('change', e => { isDesktop = e.matches; mobileOpen = false; });
      "
      :data-theme="theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title') - ZJX ARTCC</title>

    <script>
        (function () {
            var theme = localStorage.getItem('theme');
            if (theme) document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Styles / Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.8/dist/htmx.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    @livewireStyles

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="flex flex-col min-h-screen w-full overflow-x-hidden font-sans"
      @keydown.escape.window="mobileOpen = false">

<x-navbar />

<div class="flex flex-1 min-h-0 relative">

    {{-- Mobile/tablet backdrop --}}
    <div
        x-show="mobileOpen && !isDesktop"
        x-cloak
        x-transition.opacity
        @click="mobileOpen = false"
        class="fixed inset-0 bg-black/50 z-30 lg:hidden"
    ></div>

    {{--
        Sidebar
        - < lg: off-canvas drawer
        - >= lg: always-visible rail (w-14, hamburger only) that expands to w-64
    --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 max-w-[85vw] flex flex-col
               bg-base-200 text-base-content border-r border-base-300 overflow-x-clip
               transition-[width,transform] duration-200 ease-in-out
               lg:static lg:z-auto lg:max-w-none lg:shrink-0 lg:self-stretch lg:translate-x-0"
        :class="[
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
            sidebarOpen ? 'lg:w-64' : 'lg:w-14'
        ]"
        :inert="!isDesktop && !mobileOpen"
    >
        {{-- Fixed-width inner wrapper so content never reflows while the width animates --}}
        <div class="w-64 max-w-[85vw] lg:max-w-none flex flex-col flex-1 lg:sticky lg:top-0 lg:max-h-screen overflow-y-auto overflow-x-hidden">

            {{-- Header: hamburger is always visible, title only when expanded --}}
            <div class="flex items-center h-14 shrink-0">
                <button
                    type="button"
                    @click="isDesktop ? sidebarOpen = !sidebarOpen : mobileOpen = false"
                    :aria-expanded="isDesktop ? sidebarOpen : mobileOpen"
                    aria-label="Toggle sidebar"
                    class="flex items-center justify-center w-14 h-14 shrink-0 hover:bg-base-300 transition-colors"
                >
                    <span class="flex flex-col gap-1">
                        <span class="block h-0.5 w-5 bg-current rounded"></span>
                        <span class="block h-0.5 w-5 bg-current rounded"></span>
                        <span class="block h-0.5 w-5 bg-current rounded"></span>
                    </span>
                </button>
                <span class="font-semibold whitespace-nowrap">Navigation</span>
            </div>

            {{-- Menu: hidden and inert while the desktop rail is collapsed --}}
            <div
                class="flex flex-col flex-1 border-t border-base-300"
                :class="isDesktop && !sidebarOpen && 'invisible'"
                :inert="isDesktop && !sidebarOpen"
            >
                <nav class="flex-1 py-2">
                    <ul class="flex flex-col gap-1">

                        <li>
                            <a
                                href="{{ route('admin.index') }}"
                                class="flex items-center h-11 px-4 transition-colors {{ request()->routeIs('admin.index') ? 'bg-primary text-primary-content font-semibold' : 'hover:bg-base-300' }}"
                            >
                                Dashboard
                            </a>
                        </li>

                        @if(auth()->user()?->can('manage faqs'))
                            <li>
                                <a href="{{ route('admin.faqs.index') }}" class="flex items-center h-11 px-4 transition-colors hover:bg-base-300">
                                    FAQs
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()?->hasRole('events'))
                            <li x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                    class="flex items-center justify-between h-11 px-4 w-full text-left transition-colors hover:bg-base-300"
                                >
                                    <span>Events</span>
                                    <span x-text="open ? '−' : '+'" class="text-lg leading-none"></span>
                                </button>

                                <ul x-show="open" x-cloak class="bg-base-300/50 py-1 text-sm">
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('admin.events.index') }}">Manage Events</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('admin.events.position-presets.index') }}">Position Presets</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('admin.events.event-fields.index') }}">Event Field Presets</a></li>
                                    @if(auth()->user()?->hasPermissionTo('staffing-requests:read'))
                                        <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('admin.staffing-requests.index') }}">Staffing Requests</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif

                        @if(auth()->user()?->hasRole('training'))
                            <li x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                    class="flex items-center justify-between h-11 px-4 w-full text-left transition-colors hover:bg-base-300"
                                >
                                    <span>Training</span>
                                    <span x-text="open ? '−' : '+'" class="text-lg leading-none"></span>
                                </button>

                                <ul x-show="open" x-cloak class="bg-base-300/50 py-1 text-sm">
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('training-assignments.index') }}">My Students (TODO)</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('training-assignments.index') }}">Training Assignments</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('training-tickets.index') }}">Training Tickets</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('training-tickets.create') }}">Create Training Ticket</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('solo-certs.index') }}">Solo Certs</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('solo-certs.create') }}">Issue Solo Cert</a></li>
                                    @if(auth()->user()?->hasPermissionTo('certifications:write'))
                                        <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('certifications.index') }}">Certifications</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif

                        @if(auth()->user()?->hasRole('facilities'))
                            <li x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                    class="flex items-center justify-between h-11 px-4 w-full text-left transition-colors hover:bg-base-300"
                                >
                                    <span>Facilities</span>
                                    <span x-text="open ? '−' : '+'" class="text-lg leading-none"></span>
                                </button>

                                <ul x-show="open" x-cloak class="bg-base-300/50 py-1 text-sm">
                                    @if(auth()->user()?->hasPermissionTo('manage statistics prefixes'))
                                        <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a></li>
                                    @endif
                                    @if(auth()->user()?->hasPermissionTo('certification-facilities:write'))
                                        <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('certification-facilities.index') }}">Certification Facilities</a></li>
                                    @endif
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('admin.publications.index') }}">Document Management</a></li>
                                </ul>
                            </li>
                        @endif

                        @if(auth()->user()?->hasRole('admin'))
                            <li x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                    class="flex items-center justify-between h-11 px-4 w-full text-left transition-colors hover:bg-base-300"
                                >
                                    <span>Administration</span>
                                    <span x-text="open ? '−' : '+'" class="text-lg leading-none"></span>
                                </button>

                                <ul x-show="open" x-cloak class="bg-base-300/50 py-1 text-sm">
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('manage-users.index') }}">User Management</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('admin.news.index') }}">News Management</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('visit.manage') }}">Visitor Requests</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('loa.manage') }}">LOA Requests</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('logs.index') }}">Audit Log</a></li>
                                    <li><a class="block py-2 pl-8 pr-4 hover:bg-base-300" href="{{ route('roster-purge.index') }}">Purge Assistant</a></li>
                                </ul>
                            </li>
                        @endif
                    </ul>
                </nav>

                {{-- Pinned to the bottom of the sidebar --}}
                @if(auth()->user()?->hasRole('admin'))
                    <div class="mt-auto shrink-0 border-t border-base-300 py-2">
                        <a href="#" class="flex items-center h-11 px-4 transition-colors hover:bg-base-300">
                            System Settings (TODO)
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </aside>

    <div class="min-w-0 flex-1 flex flex-col">

        {{-- Mobile/tablet: sticky menu bar (hidden on desktop) --}}
        <div class="lg:hidden sticky top-0 z-30 bg-base-100 border-b border-base-300 shadow-sm">
            <button
                type="button"
                @click="mobileOpen = true"
                class="flex items-center gap-3 w-full h-14 px-4 bg-primary text-primary-content font-semibold active:opacity-90"
                aria-label="Open menu"
            >
                <span class="flex flex-col gap-1">
                    <span class="block h-0.5 w-6 bg-current rounded"></span>
                    <span class="block h-0.5 w-6 bg-current rounded"></span>
                    <span class="block h-0.5 w-6 bg-current rounded"></span>
                </span>
                <span>Menu</span>
            </button>
        </div>

        <main class="min-w-0 flex-1 p-4 sm:p-6">
            @yield('body')
        </main>

    </div>

</div>

@livewireScripts
</body>
</html>
