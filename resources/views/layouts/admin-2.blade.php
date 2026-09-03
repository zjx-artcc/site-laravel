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

<body class="flex min-h-screen w-full overflow-x-hidden font-sans">

<aside class="w-64 shrink-0 min-h-screen">
    <ul class="menu bg-base-200 min-h-full w-full p-4">
        <li><a>Dashboard</a></li>
        <li><a>Events</a></li>
        <li><a>Training</a></li>
        <li><a>Facilities</a></li>
        <li><a>System Settings</a></li>
    </ul>
</aside>

<main class="min-w-0 flex-1 p-6">
    @yield('content')
</main>

</body>
