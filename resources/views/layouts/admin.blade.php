<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ theme: localStorage.getItem('theme') }"
      x-init="$watch('theme', value => value ? localStorage.setItem('theme', value) : localStorage.removeItem('theme'))"
      :data-theme="theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title') - ZJX ARTCC</title>

    {{-- Applied before first paint so a saved preference doesn't flash the system-default theme first --}}
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

<div class="flex flex-1 min-h-0">

    <aside class="w-64 shrink-0">
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

            <li>
                <details>
                    <summary class="font-normal hover:bg-base-300">
                        Events
                    </summary>

                    <ul>
                        <li><a href="{{ route('admin.events.index') }}">Manage Events</a></li>
                        <li><a href="{{ route('admin.events.position-presets.index') }}">Position Presets</a></li>
                        <li><a href="{{ route('admin.events.event-fields.index') }}">Event Field Presets</a></li>
                        <li><a href="{{ route('admin.staffing-requests.index') }}">Staffing Requests</a></li>
                    </ul>
                </details>
            </li>

            <li>
                <details>
                    <summary class="font-normal hover:bg-base-300">
                        Training
                    </summary>

                    <ul>
                        <li><a href="{{ route('admin.training.index') }}">Training Dashboard</a></li>
                        <li><a href="{{ route('training-assignments.index') }}">My Students (TODO)</a></li>
                        <li><a href="{{ route('training-assignments.index') }}">Training Assignments</a></li>
                        <li><a href="{{ route('training-tickets.index') }}">Training Tickets</a></li>
                        <li><a href="{{ route('training-tickets.create') }}">Create Training Ticket</a></li>
                        <li><a href="{{ route('solo-certs.index') }}">Solo Certs</a></li>
                        <li><a href="{{ route('solo-certs.create') }}">Issue Solo Cert</a></li>
                    </ul>
                </details>
            </li>

            <li>
                <details>
                    <summary class="font-normal hover:bg-base-300">
                        Facilities
                    </summary>

                    <ul>
                        <li><a href="{{ route('statistics-prefixes.index') }}">Statistics Prefixes</a></li>
                        <li><a href="{{ route('certification-facilities.index') }}">Certification Management</a></li>
                        <li><a href="{{ route('admin.publications.index') }}">Document Management</a></li>
                    </ul>
                </details>
            </li>

            <li>
                <a href="#">System Settings (Under Construction)</a>
            </li>
        </ul>
    </aside>

    <main class="min-w-0 flex-1 p-6">
        @yield('content')
    </main>

</div>

@livewireScripts
</body>
<html>
