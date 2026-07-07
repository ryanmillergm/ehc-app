@php
    $rtl = (bool) ($page['right_to_left'] ?? false);
    $isFullBleed = ($page['hero_style'] ?? 'contained') === 'full_bleed';
    $heroMode = (string) ($page['hero_mode'] ?? 'none');
    $firstSlide = $page['hero_slides'][0] ?? null;
    $heroImage = match ($heroMode) {
        'slider' => $firstSlide,
        'image' => $page['hero_image'] ?? null,
        default => null,
    };
    $hasVideo = $heroMode === 'video' && !empty($page['hero_video']);
    $hasImage = is_array($heroImage) && !empty($heroImage['url']);
    $hasMedia = $hasVideo || $hasImage;
    $heightClass = match ((string) ($page['hero_height'] ?? '80')) {
        '70' => 'min-h-[58vh] md:min-h-[74vh]',
        '100' => $isFullBleed
            ? 'min-h-[calc(100svh-4rem)] md:min-h-[calc(100vh-4rem)]'
            : 'min-h-[calc(100svh-8rem)] sm:min-h-[calc(100svh-9rem)] md:min-h-[calc(100vh-9rem)]',
        default => 'min-h-[64vh] md:min-h-[88vh]',
    };
    $overlayClass = match ((string) ($page['hero_overlay'] ?? 'medium')) {
        'none' => 'bg-gradient-to-t from-slate-950/70 via-slate-950/20 to-transparent',
        'light' => 'bg-gradient-to-t from-slate-950/75 via-slate-950/30 to-slate-950/10',
        'dark' => 'bg-gradient-to-t from-slate-950/95 via-slate-950/70 to-slate-950/30',
        default => 'bg-gradient-to-t from-slate-950/90 via-slate-950/50 to-slate-950/20',
    };
    $textAlignClass = match ((string) ($page['hero_text_align'] ?? 'left')) {
        'center' => 'text-center items-center mx-auto',
        'right' => 'text-right items-end ml-auto',
        default => 'text-left items-start',
    };
    $textWidthClass = match ((string) ($page['hero_text_width'] ?? 'normal')) {
        'narrow' => 'max-w-2xl',
        'wide' => 'max-w-6xl',
        default => 'max-w-4xl',
    };
    $hasCta = !empty($page['hero_cta_text']) && !empty($page['hero_cta_url']);
@endphp

<article data-page-template="immersive" class="overflow-x-hidden bg-slate-950 text-white" @if($rtl) dir="rtl" @endif>
    <section class="{{ $isFullBleed ? 'relative left-1/2 right-1/2 -mx-[50vw] w-screen overflow-hidden' : 'mx-auto max-w-screen-2xl px-5 py-8 sm:px-8 sm:py-10 lg:px-12 2xl:px-20' }}">
        <div class="relative {{ $heightClass }} {{ $isFullBleed ? '' : 'overflow-hidden rounded-2xl border border-white/10 shadow-2xl' }} bg-slate-950">
            @if ($hasVideo)
                <div class="absolute inset-0">
                    <x-media.video
                        :video="$page['hero_video']"
                        variant="hero"
                        layout="full_bleed"
                        :min-height="(string) ($page['hero_height'] ?? '80')"
                        :rounded="false"
                    />
                </div>
            @elseif ($hasImage)
                <img src="{{ $heroImage['url'] }}" alt="{{ $heroImage['alt'] ?? $page['title'] }}" class="absolute inset-0 h-full w-full object-cover" />
            @else
                <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-rose-950"></div>
            @endif

            <div class="absolute inset-0 {{ $overlayClass }}"></div>
            <div class="relative mx-auto flex {{ $heightClass }} max-w-screen-2xl items-end px-5 pb-12 pt-24 sm:px-8 sm:pb-16 lg:px-12 2xl:px-20">
                <div class="min-w-0 w-full {{ $textAlignClass }} {{ $textWidthClass }} space-y-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-white/70">Feature</p>
                    <h1 class="break-words text-3xl font-black leading-[1.03] tracking-tight sm:text-6xl lg:text-7xl">
                        {!! $page['hero_title'] ?? $page['title'] !!}
                    </h1>
                    @if (!empty($page['hero_subtitle']))
                        <div class="max-w-3xl text-lg leading-8 text-white/90 sm:text-xl">
                            {!! $page['hero_subtitle'] !!}
                        </div>
                    @endif
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center {{ (string) ($page['hero_text_align'] ?? 'left') === 'center' ? 'sm:justify-center' : ((string) ($page['hero_text_align'] ?? 'left') === 'right' ? 'sm:justify-end' : '') }}">
                        @if ($hasCta)
                            <a href="{{ $page['hero_cta_url'] }}" class="inline-flex items-center justify-center rounded-full bg-white px-7 py-3.5 text-sm font-bold text-slate-950 transition hover:bg-slate-100">
                                {!! $page['hero_cta_text'] !!}
                            </a>
                        @endif
                        <a href="#immersive-content" class="inline-flex items-center justify-center rounded-full bg-white/10 px-7 py-3.5 text-sm font-bold text-white ring-1 ring-white/20 transition hover:bg-white/20">
                            Read more
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="immersive-content" class="bg-white text-slate-900">
        <div class="mx-auto grid max-w-screen-2xl gap-10 px-5 py-12 sm:px-8 sm:py-16 lg:grid-cols-12 lg:px-12 2xl:px-20">
            <div class="lg:col-span-4">
                <div class="sticky top-24 border-t border-slate-200 pt-5">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-700">In focus</p>
                    <div class="mt-3 text-lg font-semibold leading-8 text-slate-800">
                        {!! $page['description'] !!}
                    </div>
                    @if ($hasCta)
                        <a href="{{ $page['hero_cta_url'] }}" class="mt-6 inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                            {!! $page['hero_cta_text'] !!}
                        </a>
                    @endif
                </div>
            </div>
            <div class="lg:col-span-8">
                <div class="prose prose-lg prose-slate max-w-none prose-headings:font-black prose-headings:tracking-tight prose-p:leading-9 prose-li:leading-8">
                    {!! $page['content'] !!}
                </div>
            </div>
        </div>
    </section>

    @if (!empty($page['content_blocks']))
        <section class="bg-slate-100 text-slate-900">
            <div class="mx-auto max-w-screen-2xl space-y-8 px-5 py-12 sm:px-8 lg:px-12 2xl:px-20">
                @foreach ($page['content_blocks'] as $block)
                    @includeIf('livewire.pages.blocks.' . ($block['type'] ?? ''), ['data' => $block['data'] ?? [], 'page' => $page])
                @endforeach
            </div>
        </section>
    @endif
</article>
