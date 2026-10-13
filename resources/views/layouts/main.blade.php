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
    <body class='flex flex-col min-h-screen w-full overflow-x-hidden font-sans'>
        <x-navbar/>

        @yield('secondary-navbar')

        <div
            x-data="{
                alerts: [
                    @if ($errors->any())
                        { id: 'validation', type: 'error', message: @js(implode(' ', $errors->all())) },
                    @endif
                    @if (session('error'))
                        { id: 'session-error', type: 'error', message: @js(session('error')) },
                    @endif
                    @if (session('success'))
                        { id: 'session-success', type: 'success', message: @js(session('success')) },
                    @endif
                ]
            }"
            x-on:notify.window="alerts.push({ id: Date.now() + '-' + Math.random(), type: $event.detail.type ?? 'info', message: $event.detail.message })"
            class="flex flex-col gap-2 px-2 sm:px-0"
        >
            <template x-for="alert in alerts" :key="alert.id">
                <div
                    role="alert"
                    class="alert"
                    :class="{
                        'alert-error': alert.type === 'error',
                        'alert-success': alert.type === 'success',
                        'alert-warning': alert.type === 'warning',
                        'alert-info': alert.type === 'info',
                    }"
                >
                    <span x-text="alert.message"></span>
                    <button type="button" class="btn btn-ghost btn-sm" @click="alerts = alerts.filter(a => a.id !== alert.id)">Close</button>
                </div>
            </template>
        </div>

        @yield('body-nopad')

        @unless($__env->hasSection('hide-heading'))
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 ml-5 mt-5">
                <h1 class='font-bold text-2xl'>@yield('title')</h1>
                @hasSection('title-extra')
                    @yield('title-extra')
                @endif
            </div>
        @endunless

        <div class="p-5 flex-1">
            @yield('body')
        </div>

        <div class="footer gap-y-0 p-0">
            @env('development', 'staging')
                <div class="footer-center w-full p-2 bg-warning text-warning-content">
                    <h1>DEVELOPMENT BUILD - THE FUNCTIONS OF THIS SITE ARE NOT INDICATIVE OF THE PRODUCTION WEBSITE AND MAY BE CHANGED AT ANY TIME. SENSITIVE DATA IS ENTERED AT YOUR OWN RISK.</h1>
                    @auth
                        <h1 class="mt-5">AUTHENTICATED USER: <strong>{{Auth::user()->id}} - {{Auth::user()->name}}</strong></h1>
                        <h1>SESSION: <strong>{{Auth::getSession()->getId()}} - {{Auth::getSession()->getName()}}</strong></h1>
                        <span>
                            <h1>PERMISSIONS:</h1>
                            <p>
                                @foreach(Auth::user()->getAllPermissions() as $perm)
                                    {{$perm->name}} //
                                @endforeach
                            </p>
                        </span>
                    @endauth

                    @guest
                        <h1>USER NOT AUTHENTICATED</h1>
                    @endguest
                </div>
            @endenv

            <footer class="w-full bg-primary text-primary-content p-2">
                <h1 class="text-xl font-bold">Virtual Jacksonville ARTCC</h1>

                <div class="flex flex-wrap gap-x-6 sm:gap-x-10 gap-y-1">
                    <a class="link text-lg" href="{{ route('faq.index') }}">FAQ &amp; Help</a>
                    <a class="link text-lg" href="https://github.com/zjx-artcc" target="_blank">GitHub</a>
                    <a class="link text-lg" href="https://vatusa.net" target="_blank">VATUSA</a>
                    <a class="link text-lg" href="https://vatsim.net" target="_blank">VATSIM</a>
                    <a class="link text-lg" href="{{ route('contributors.index') }}">Contributors</a>
                </div>

                <p class="text-md">The content of this website was developed for the Virtual Jacksonville ARTCC (vZJX). vZJX has <strong>no affiliation</strong> to the real Jacksonville ARTCC, the Federal Aviation Administration, or any governing aviation authority, nor does vZJX intend to impersonate them in any way, shape or form. This site should never be used for purposes including flight planning, air traffic control, air traffic management, or any relavant operations</p>
                <p class="text-md">vZJX is a subdivision of VATUSA and VATNA on the VATSIM network.</p>
            </footer>
        </div>
        @livewireScripts
    </body>
</html>
