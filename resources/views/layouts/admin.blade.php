<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ theme: localStorage.getItem('theme'), sidebarOpen: false }"
      x-init="$watch('theme', value => value ? localStorage.setItem('theme', value) : localStorage.removeItem('theme'))"
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

<body class="flex flex-col min-h-screen w-full overflow-x-hidden font-sans">

<x-navbar />

<div class="flex flex-1 min-h-0 relative">

    <button
        @click="sidebarOpen = !sidebarOpen"
        class="btn btn-square btn-ghost fixed bottom-4 right-4 z-30 shadow-md bg-primary text-white"
    >
        <i class="fa-solid fa-bars"></i>
    </button>

    <div
        x-show="sidebarOpen"
        x-cloak
        @click="sidebarOpen = false"
        class="fixed inset-0 bg-black/50 z-20"
    ></div>

    <aside
        class="w-64 max-w-[85vw] shrink-0 fixed inset-y-0 left-0 z-20 overflow-y-auto transform transition-transform duration-200 ease-in-out"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <ul class="menu flex-nowrap bg-base-200 min-h-full w-full p-4">
        <ul class="menu bg-base-200 min-h-full w-full p-4">
            <li>
                <a
                    href="{{ route('admin.index') }}"
                    class="{{ request()->routeIs('admin.index')
                            ? 'bg-primary text-primary-content'
                            : 'hover:bg-base-300' }}"
                >
                    Dashboard
                </a>
            </li>

            @if(auth()->user()?->can('manage faqs'))
                <li><a href="{{ route('admin.faqs.index') }}" class="hover:bg-base-300">FAQs</a></li>
            @endif

            @if(auth()->user()?->hasRole('events'))
                <li>
                    <details>
                        <summary class="font-normal hover:bg-base-300">
                            Events
                        </summary>

                        <ul>
                            <li><a href="{{ route('admin.events.index') }}">Manage Events</a></li>
                            <li><a href="{{ route('admin.events.position-presets.index') }}">Position Presets</a></li>
                            <li><a href="{{ route('admin.events.event-fields.index') }}">Event Field Presets</a></li>
                            @if(auth()->user()?->hasPermissionTo('staffing-requests:read'))
                                <li><a href="{{ route('admin.staffing-requests.index') }}">Staffing Requests</a></li>
                            @endif
                        </ul>
                    </details>
                </li>
            @endif

            @if(auth()->user()?->hasRole('training'))
                <li>
                    <details>
                        <summary class="font-normal hover:bg-base-300">
                            Training
                        </summary>

                        <ul>
                            <li><a href="{{ route('training-assignments.index') }}">My Students (TODO)</a></li>
                            <li><a href="{{ route('training-assignments.index') }}">Training Assignments</a></li>
                            <li><a href="{{ route('training-tickets.index') }}">Training Tickets</a></li>
                            <li><a href="{{ route('training-tickets.create') }}">Create Training Ticket</a></li>
                            <li><a href="{{ route('solo-certs.index') }}">Solo Certs</a></li>
                            <li><a href="{{ route('solo-certs.create') }}">Issue Solo Cert</a></li>
                            @if(auth()->user()?->hasPermissionTo('certifications:write'))
                                <li><a href="{{ route('certifications.index') }}">Certifications</a></li>
                            @endif
                        </ul>
                    </details>
                </li>
            @endif

            @if(auth()->user()?->hasRole('facilities'))
                <li>
                    <details>
                        <summary class="font-normal hover:bg-base-300">
                            Facilities
                        </summary>

                        <ul>
                            @if(auth()->user()?->hasPermissionTo('manage statistics prefixes'))
                                <li><a href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a></li>
                            @endif
                            @if(auth()->user()?->hasPermissionTo('certification-facilities:write'))
                                <li><a href="{{ route('certification-facilities.index') }}">Certification Facilities</a></li>
                            @endif
                            <li><a href="{{ route('admin.publications.index') }}">Document Management</a></li>
                        </ul>
                    </details>
                </li>
            @endif

            @if(auth()->user()?->hasRole('admin'))
                <li>
                    <details>
                        <summary class="font-normal hover:bg-base-300">
                            Administration
                        </summary>

                        <ul>
                            <li><a href="{{ route('manage-users.index') }}">User Management</a></li>
                            <li><a href="{{ route('admin.news.index') }}">News Management</a></li>
                            <li><a href="{{ route('visit.manage') }}">Visitor Requests</a></li>
                            <li><a href="{{ route('loa.manage') }}">LOA Requests</a></li>
                            <li><a href="{{ route('logs.index') }}">Audit Log</a></li>
                            <li><a href="{{ route('logs.index') }}">Stats Sync</a></li>
                        </ul>
                    </details>
                </li>

                <li>
                    <a href="#">System Settings (TODO)</a>
                </li>
            @endif
        </ul>
    </aside>

    <main class="min-w-0 flex-1 p-6">
        @yield('body')
    </main>

</div>

@livewireScripts
</body>
</html>
