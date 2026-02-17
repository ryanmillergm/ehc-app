{{-- Layout For General Auth User App --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $siteName = config('app.name', 'Bread of Grace Ministries');
            $defaultTitle = $title ?? $siteName;
            $metaTitle = $metaTitle ?? $defaultTitle;
            $metaDescription = $metaDescription ?? 'Bread of Grace Ministries in Sacramento, California serves people experiencing homelessness through meals, outreach, housing pathways, and Christ-centered support.';
            $metaRobots = $metaRobots ?? 'index,follow';
            $canonicalUrl = $canonicalUrl ?? url()->current();
            $ogType = $ogType ?? 'website';
            $ogTitle = $ogTitle ?? $metaTitle;
            $ogDescription = $ogDescription ?? $metaDescription;
            $ogImage = $ogImage ?? asset('images/sm/the-mayor.jpg');
            $twitterCard = $twitterCard ?? 'summary_large_image';
            $twitterTitle = $twitterTitle ?? $ogTitle;
            $twitterDescription = $twitterDescription ?? $ogDescription;
            $twitterImage = $twitterImage ?? $ogImage;
            $seoJsonLd = $seoJsonLd ?? [];
        @endphp

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="robots" content="{{ $metaRobots }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

        <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('images/favicons/apple-icon-57x57.png') }}">
        <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('images/favicons/apple-icon-60x60.png') }}">
        <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('images/favicons/apple-icon-72x72.png') }}">
        <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('images/favicons/apple-icon-76x76.png') }}">
        <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('images/favicons/apple-icon-114x114.png') }}">
        <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('images/favicons/apple-icon-120x120.png') }}">
        <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('images/favicons/apple-icon-144x144.png') }}">
        <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('images/favicons/apple-icon-152x152.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-icon-180x180.png') }}">
        <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('images/favicons/android-icon-192x192.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicons/favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/favicons/favicon-96x96.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicons/favicon-16x16.png') }}">
        <link rel="manifest" href="{{ asset('images/favicons/manifest.json') }}">
        <meta name="msapplication-TileColor" content="#ffffff">
        <meta name="msapplication-TileImage" content="{{ asset('images/favicons/ms-icon-144x144.png') }}" >
        <meta name="theme-color" content="#ffffff">

        <title>{{ $metaTitle }}</title>

        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $ogImage }}">

        <meta name="twitter:card" content="{{ $twitterCard }}">
        <meta name="twitter:title" content="{{ $twitterTitle }}">
        <meta name="twitter:description" content="{{ $twitterDescription }}">
        <meta name="twitter:image" content="{{ $twitterImage }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style></style>
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

        @foreach ($seoJsonLd as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach

        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <livewire:navbar />

        <main class="pt-16">
            <x-banner />

            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
