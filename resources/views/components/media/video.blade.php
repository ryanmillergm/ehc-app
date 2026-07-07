@props([
    'video' => null,
    'variant' => 'inline',
    'fit' => null,
    'aspect' => '16:9',
    'autoplay' => false,
    'muted' => false,
    'loop' => false,
    'controls' => true,
    'preload' => 'metadata',
    'layout' => null,
    'minHeight' => null,
    'rounded' => true,
])

@php
    $video = is_array($video) ? $video : null;
    $src = $video['url'] ?? null;
    $sourceType = $video['source_type'] ?? null;
    $poster = $video['poster_url'] ?? null;
    $isHero = $variant === 'hero';
    $layout = $layout ?: ($isHero ? 'contained' : 'contained');
    $isHeroBackground = $isHero && $layout === 'full_bleed';

    $containerClass = match (true) {
        $layout === 'full_bleed' => 'relative overflow-hidden',
        $isHero => 'relative overflow-hidden ' . ($rounded ? 'rounded-3xl' : '') . ' shadow-lg',
        default => 'relative overflow-hidden ' . ($rounded ? 'rounded-2xl' : ''),
    };

    $ratioClass = match ($aspect) {
        '21:9' => 'aspect-[21/9]',
        'auto' => '',
        default => 'aspect-video',
    };

    if ($layout === 'full_bleed') {
        $ratioClass = '';
    }

    $fitClass = $fit ?: ($isHero ? 'object-cover' : 'object-contain');

    $heightClass = match ((string) $minHeight) {
        '70' => 'min-h-[50vh] md:min-h-[70vh]',
        '100' => 'min-h-[58vh] md:min-h-screen',
        default => 'min-h-[55vh] md:min-h-[80vh]',
    };

    $host = is_string($src) ? strtolower((string) parse_url($src, PHP_URL_HOST)) : '';
    $hostMatches = static fn (string $candidate, string $domain): bool => $candidate === $domain || str_ends_with($candidate, '.' . $domain);
    $isYoutube = $hostMatches($host, 'youtube.com') || $hostMatches($host, 'youtube-nocookie.com') || $host === 'youtu.be';
    $isVimeo = $hostMatches($host, 'vimeo.com') || $hostMatches($host, 'player.vimeo.com');
    $isAllowedEmbed = $isYoutube || $isVimeo;

    $buildEmbedUrl = static function (string $url, array $forcedParams): string {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query = array_merge($query, $forcedParams);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port . $path . ($queryString !== '' ? '?' . $queryString : '') . $fragment;
    };

    $extractYoutubeId = static function (string $url): ?string {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');

        if ($host === 'youtu.be' && $path !== '') {
            return explode('/', $path)[0] ?: null;
        }

        if (preg_match('~(?:embed|shorts)/([^/?#]+)~', $path, $matches)) {
            return $matches[1];
        }

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $videoId = $query['v'] ?? null;

            return is_string($videoId) && $videoId !== '' ? $videoId : null;
        }

        return null;
    };

    $embedSrc = $src;
    if (is_string($src) && $isHeroBackground && $isYoutube) {
        $youtubeId = $extractYoutubeId($src);
        $params = [
            'autoplay' => '1',
            'mute' => '1',
            'loop' => '1',
            'controls' => '0',
            'playsinline' => '1',
            'rel' => '0',
            'modestbranding' => '1',
            'iv_load_policy' => '3',
            'disablekb' => '1',
            'fs' => '0',
        ];

        if ($youtubeId) {
            $params['playlist'] = $youtubeId;
        }

        $embedSrc = $buildEmbedUrl($src, $params);
    } elseif (is_string($src) && $isHeroBackground && $isVimeo) {
        $embedSrc = $buildEmbedUrl($src, [
            'background' => '1',
            'autoplay' => '1',
            'muted' => '1',
            'loop' => '1',
            'controls' => '0',
        ]);
    }

    $effectiveAutoplay = $isHeroBackground ? true : (bool) $autoplay;
    $effectiveMuted = $isHeroBackground ? true : (bool) $muted;
    $effectiveLoop = $isHeroBackground ? true : (bool) $loop;
    $effectiveControls = $isHeroBackground ? false : (bool) $controls;
    $effectivePreload = $isHeroBackground ? 'auto' : $preload;
    $iframeClass = $isHeroBackground
        ? 'pointer-events-none absolute left-1/2 top-1/2 h-[56.25vw] min-h-[135%] w-[177.78vh] min-w-[135%] max-w-none -translate-x-1/2 -translate-y-1/2 border-0'
        : 'h-full w-full ' . ($layout === 'full_bleed' ? 'absolute inset-0' : '');
    $videoClass = 'h-full w-full ' . $fitClass . ' ' . ($layout === 'full_bleed' ? 'absolute inset-0' : '');
@endphp

@if ($src)
    <div {{ $attributes->class([$containerClass, $ratioClass, $layout === 'full_bleed' ? $heightClass : '']) }} @if($isHeroBackground) data-hero-background-video="true" @endif>
        @if ($sourceType === 'embed' && $isAllowedEmbed)
            <iframe
                src="{{ $embedSrc }}"
                class="{{ $iframeClass }}"
                title="{{ $video['title'] ?? 'Embedded video' }}"
                loading="{{ $isHeroBackground ? 'eager' : 'lazy' }}"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                @if(! $isHeroBackground) allowfullscreen @endif
                @if($isHeroBackground) aria-hidden="true" tabindex="-1" @endif
                referrerpolicy="strict-origin-when-cross-origin"
            ></iframe>
        @elseif ($sourceType === 'upload')
            <video
                src="{{ $src }}"
                class="{{ $videoClass }}"
                @if($poster) poster="{{ $poster }}" @endif
                @if($effectiveAutoplay) autoplay @endif
                @if($effectiveMuted) muted @endif
                @if($effectiveLoop) loop @endif
                @if($effectiveControls) controls @endif
                preload="{{ $effectivePreload }}"
                playsinline
            ></video>
        @endif
    </div>
@endif
