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
])

@php
    $video = is_array($video) ? $video : null;
    $src = $video['url'] ?? null;
    $sourceType = $video['source_type'] ?? null;
    $poster = $video['poster_url'] ?? null;
    $isHero = $variant === 'hero';

    $containerClass = $isHero
        ? 'relative overflow-hidden rounded-3xl shadow-lg'
        : 'relative overflow-hidden rounded-2xl';

    $ratioClass = match ($aspect) {
        '21:9' => 'aspect-[21/9]',
        'auto' => '',
        default => 'aspect-video',
    };

    $fitClass = $fit ?: ($isHero ? 'object-cover' : 'object-contain');

    $isYoutube = is_string($src) && (str_contains($src, 'youtube.com') || str_contains($src, 'youtu.be'));
    $isVimeo = is_string($src) && str_contains($src, 'vimeo.com');
    $isAllowedEmbed = $isYoutube || $isVimeo;
@endphp

@if ($src)
    <div {{ $attributes->class([$containerClass, $ratioClass]) }}>
        @if ($sourceType === 'embed' && $isAllowedEmbed)
            <iframe
                src="{{ $src }}"
                class="h-full w-full"
                title="{{ $video['title'] ?? 'Embedded video' }}"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
            ></iframe>
        @elseif ($sourceType === 'upload')
            <video
                src="{{ $src }}"
                class="h-full w-full {{ $fitClass }}"
                @if($poster) poster="{{ $poster }}" @endif
                @if($autoplay) autoplay @endif
                @if($muted) muted @endif
                @if($loop) loop @endif
                @if($controls) controls @endif
                preload="{{ $preload }}"
                playsinline
            ></video>
        @endif
    </div>
@endif
