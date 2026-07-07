@php
    $isFullBleed = ($data['hero_style'] ?? 'contained') === 'full_bleed';
    $heroMode = (string) ($data['hero_mode'] ?? 'none');
    $firstSlide = $page['hero_slides'][0] ?? null;
    $heroImage = match ($heroMode) {
        'slider' => $firstSlide ?: ($page['hero_image'] ?? null),
        'image' => $page['hero_image'] ?? null,
        default => null,
    };
    $hasVideo = $heroMode === 'video' && !empty($page['hero_video']);
    $hasImage = is_array($heroImage) && !empty($heroImage['url']);
    $hasMedia = $hasVideo || $hasImage;
    $heightClass = match ((string) ($data['hero_height'] ?? '80')) {
        '70' => 'min-h-[46vh] md:min-h-[62vh]',
        '100' => $isFullBleed
            ? 'min-h-[calc(100svh-4rem)] md:min-h-[calc(100vh-4rem)]'
            : 'min-h-[calc(100svh-8rem)] sm:min-h-[calc(100svh-9rem)] md:min-h-[calc(100vh-9rem)]',
        default => 'min-h-[50vh] md:min-h-[72vh]',
    };
    $overlayClass = match ((string) ($data['hero_overlay'] ?? 'medium')) {
        'none' => '',
        'light' => 'bg-slate-950/25',
        'dark' => 'bg-slate-950/70',
        default => 'bg-slate-950/45',
    };
    $textAlignClass = match ((string) ($data['hero_text_align'] ?? 'left')) {
        'center' => 'text-center items-center mx-auto',
        'right' => 'text-right items-end ml-auto',
        default => 'text-left items-start',
    };
    $buttonAlignClass = match ((string) ($data['hero_text_align'] ?? 'left')) {
        'center' => 'justify-center',
        'right' => 'justify-end',
        default => '',
    };
    $textWidthClass = match ((string) ($data['hero_text_width'] ?? 'normal')) {
        'narrow' => 'max-w-2xl',
        'wide' => 'max-w-5xl',
        default => 'max-w-3xl',
    };
@endphp

<section
    data-page-block="hero"
    data-block-hero-mode="{{ $heroMode }}"
    class="{{ $isFullBleed ? 'relative left-1/2 right-1/2 -mx-[50vw] w-screen overflow-hidden' : 'mx-auto max-w-screen-2xl px-5 py-8 sm:px-8 sm:py-10 lg:px-12 2xl:px-20' }}"
>
    <div class="relative overflow-hidden {{ $isFullBleed ? '' : 'rounded-2xl border border-slate-200 shadow-lg' }} {{ $heightClass }} bg-slate-950 text-white">
        @if ($hasVideo)
            <div class="absolute inset-0">
                <x-media.video
                    :video="$page['hero_video']"
                    variant="hero"
                    layout="full_bleed"
                    :min-height="(string) ($data['hero_height'] ?? '80')"
                    :rounded="false"
                />
            </div>
        @elseif ($hasImage)
            <img src="{{ $heroImage['url'] }}" alt="{{ $heroImage['alt'] ?? $page['title'] }}" class="absolute inset-0 h-full w-full object-cover" />
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-rose-950"></div>
        @endif

        @if ($overlayClass !== '')
            <div class="absolute inset-0 {{ $overlayClass }}"></div>
        @endif

        <div class="relative mx-auto flex {{ $heightClass }} max-w-screen-2xl items-center px-5 py-12 sm:px-8 lg:px-12 2xl:px-20">
            <div class="flex min-w-0 w-full flex-col {{ $textAlignClass }} {{ $textWidthClass }} space-y-5">
                @if (!empty($data['eyebrow']))
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/75">{!! $data['eyebrow'] !!}</p>
                @endif

                <h2 class="break-words text-3xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                    {!! $data['heading'] ?? $page['title'] !!}
                </h2>

                @if (!empty($data['subheading']))
                    <div class="max-w-3xl text-base leading-8 text-white/90 sm:text-lg">
                        {!! $data['subheading'] !!}
                    </div>
                @endif

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center {{ $buttonAlignClass }}">
                    @if (!empty($data['primary_cta_text']) && !empty($data['primary_cta_url']))
                        <a class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-bold text-slate-950 transition hover:bg-slate-100" href="{{ $data['primary_cta_url'] }}">{!! $data['primary_cta_text'] !!}</a>
                    @endif

                    @if (!empty($data['secondary_cta_text']) && !empty($data['secondary_cta_url']))
                        <a class="inline-flex items-center justify-center rounded-full bg-white/10 px-6 py-3 text-sm font-bold text-white ring-1 ring-white/25 transition hover:bg-white/20" href="{{ $data['secondary_cta_url'] }}">{!! $data['secondary_cta_text'] !!}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
