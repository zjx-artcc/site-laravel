<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title') - ZJX ARTCC</title>

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
    <body class='flex flex-col min-h-screen w-full overflow-x-hidden font-sans' data-theme='light'>
        <x-navbar/>

        @yield('secondary-navbar')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('error'))
            <div x-data="{open: true}" x-show='open' class="alert alert-error alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class='btn btn-ghost cursor-pointer' x-on:click='open = false'>Close</button>
            </div>
        @endif

        @if(session('success'))
            <div x-data="{open: true}" x-show='open' class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class='btn btn-ghost cursor-pointer' x-on:click='open = false'>Close</button>
            </div>
        @endif

        @yield('body-nopad')

        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 ml-5 mt-5">
            <h1 class='font-bold text-2xl'>@yield('title')</h1>
            @hasSection('title-extra')
                @yield('title-extra')
            @endif
        </div>

        <div class="p-5 flex-1">
            @yield('body')
        </div>

        <div class="footer gap-y-0 p-0">
            @env('development')
                <div class="footer-center w-full p-2 bg-warning">
                    <h1>DEVELOPMENT BUILD - THE FUNCTIONS OF THIS SITE ARE NOT INDICATIVE OF THE PRODUCTION WEBSITE AND MAY BE CHANGED AT ANY TIME. SENSITIVE DATA IS ENTERED AT YOUR OWN RISK.</h1>
                    @auth
                        <h1 class="mt-5">AUTHENTICATED USER: <strong>{{Auth::user()->id}} - {{Auth::user()->name}}</strong></h1>
                        <h1>SESSION: <strong>{{Auth::getSession()->getId()}} - {{Auth::getSession()->getName()}}</strong></h1>
                    @endauth

                    @guest
                        <h1>USER NOT AUTHENTICATED</h1>
                    @endguest
                </div>
            @endenv

            <footer class="w-full bg-primary text-primary-content p-2">
                <h1 class="text-xl font-bold">Virtual Jacksonville ARTCC</h1>

                <div class="flex gap-x-10">
                    <a class="link text-lg" href="https://github.com/zjx-artcc" target="_blank">Join vZJX</a>
                    <a class="link text-lg" href="https://github.com/zjx-artcc" target="_blank">GitHub</a>
                    <a class="link text-lg" href="https://vatusa.net" target="_blank">VATUSA</a>
                    <a class="link text-lg" href="https://vatsim.net" target="_blank">VATSIM</a>
                </div>

                <p class="text-md">The content of this website was developed for the Virtual Jacksonville ARTCC (vZJX). vZJX has <strong>no affiliation</strong> to the real Jacksonville ARTCC, the Federal Aviation Administration, or any governing aviation authority, nor does vZJX intend to impersonate them in any way, shape or form. This site should never be used for purposes including flight planning, air traffic control, air traffic management, or any relavant operations</p>
                <p class="text-md">vZJX is a subdivision of VATUSA and VATNA on the VATSIM network.</p>
            </footer>
        </div>
        @livewireScripts
    </body>
</html>
