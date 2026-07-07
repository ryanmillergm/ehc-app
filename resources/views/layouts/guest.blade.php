<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('components.seo.head')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">
        <livewire:navbar />

        <div class="pt-16">
            <x-banner />

            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
