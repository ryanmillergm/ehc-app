@php
    $siteName = config('seo.site_name', config('app.name', 'Bread of Grace Ministries'));
    $defaultTitle = $title ?? config('seo.default_title', $siteName);
    $metaTitle = $metaTitle ?? $defaultTitle;
    $metaDescription = $metaDescription ?? config('seo.default_description');
    $metaRobots = $metaRobots ?? config('seo.robots.indexable', 'index,follow');
    $canonicalUrl = $canonicalUrl ?? url()->current();
    $ogType = $ogType ?? 'website';
    $ogTitle = $ogTitle ?? $metaTitle;
    $ogDescription = $ogDescription ?? $metaDescription;
    $defaultOgImage = config('seo.default_og_image', '/images/sm/the-mayor.jpg');
    $resolvedDefaultOgImage = str_starts_with((string) $defaultOgImage, 'http')
        ? $defaultOgImage
        : asset(ltrim((string) $defaultOgImage, '/'));
    $ogImage = $ogImage ?? $resolvedDefaultOgImage;
    $twitterCard = $twitterCard ?? config('seo.default_twitter_card', 'summary_large_image');
    $twitterTitle = $twitterTitle ?? $ogTitle;
    $twitterDescription = $twitterDescription ?? $ogDescription;
    $twitterImage = $twitterImage ?? $ogImage;
    $seoJsonLd = $seoJsonLd ?? [];
    $googleSiteVerification = config('seo.google_site_verification');
    $ga4MeasurementId = config('seo.ga4_measurement_id');
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="{{ $metaDescription }}">
<meta name="robots" content="{{ $metaRobots }}">
<link rel="canonical" href="{{ $canonicalUrl }}">

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
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicons/android-icon-192x192.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicons/favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/favicons/favicon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicons/favicon-16x16.png') }}">
<link rel="manifest" href="{{ asset('images/favicons/manifest.json') }}">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="{{ asset('images/favicons/ms-icon-144x144.png') }}">
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

@if (filled($googleSiteVerification))
    <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
@endif

@if (filled($ga4MeasurementId))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4MeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $ga4MeasurementId }}', { 'anonymize_ip': true });
    </script>
@endif

@foreach ($seoJsonLd as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
