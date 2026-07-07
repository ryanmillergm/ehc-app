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
        '70' => 'min-h-[44vh] md:min-h-[58vh]',
        '100' => 'min-h-[54vh] md:min-h-[76vh]',
        default => 'min-h-[48vh] md:min-h-[64vh]',
    };
    $overlayClass = match ((string) ($page['hero_overlay'] ?? 'medium')) {
        'none' => '',
        'light' => 'bg-slate-950/20',
        'dark' => 'bg-slate-950/70',
        default => 'bg-slate-950/40',
    };
    $textAlignClass = match ((string) ($page['hero_text_align'] ?? 'left')) {
        'center' => 'text-center items-center mx-auto',
        'right' => 'text-right items-end ml-auto',
        default => 'text-left items-start',
    };
    $textWidthClass = match ((string) ($page['hero_text_width'] ?? 'normal')) {
        'narrow' => 'max-w-2xl',
        'wide' => 'max-w-4xl',
        default => 'max-w-3xl',
    };
    $toneClass = match ($page['theme'] ?? 'default') {
        'warm' => 'bg-gradient-to-br from-white via-rose-50 to-amber-50',
        'slate' => 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white',
        default => 'bg-gradient-to-br from-white via-slate-50 to-sky-50',
    };
    $hasCta = !empty($page['hero_cta_text']) && !empty($page['hero_cta_url']);
@endphp

<article data-page-template="standard" class="overflow-x-hidden bg-white text-slate-900" @if($rtl) dir="rtl" @endif>
    <section class="{{ $isFullBleed ? 'relative left-1/2 right-1/2 -mx-[50vw] w-screen' : 'mx-auto max-w-screen-2xl px-5 sm:px-8 lg:px-12 2xl:px-20 pt-8 sm:pt-10' }}">
        <div class="relative overflow-hidden {{ $isFullBleed ? '' : 'rounded-2xl border border-slate-200 shadow-sm' }} {{ $hasMedia ? $heightClass . ' bg-slate-950 text-white' : $toneClass }}">
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
            @endif

            @if ($hasMedia)
                <div class="absolute inset-0 {{ $overlayClass }}"></div>
            @endif

            <div class="relative flex {{ $hasMedia ? $heightClass : 'min-h-[22rem]' }} items-center px-5 py-10 sm:px-8 lg:px-12">
                <div class="min-w-0 w-full {{ $textAlignClass }} {{ $textWidthClass }} space-y-5">
                    <h1 class="break-words text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                        {!! $page['hero_title'] ?? $page['title'] !!}
                    </h1>
                    @if (!empty($page['hero_subtitle']))
                        <div class="max-w-2xl text-lg leading-relaxed {{ $hasMedia || ($page['theme'] ?? 'default') === 'slate' ? 'text-white/90' : 'text-slate-700' }}">
                            {!! $page['hero_subtitle'] !!}
                        </div>
                    @endif
                    @if ($hasCta)
                        <a href="{{ $page['hero_cta_url'] }}" class="inline-flex items-center justify-center rounded-full {{ $hasMedia || ($page['theme'] ?? 'default') === 'slate' ? 'bg-white text-slate-950 hover:bg-slate-100' : 'bg-slate-950 text-white hover:bg-slate-800' }} px-6 py-3 text-sm font-semibold transition">
                            {!! $page['hero_cta_text'] !!}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-screen-2xl gap-10 px-5 py-12 sm:px-8 sm:py-16 lg:grid-cols-12 lg:px-12 2xl:px-20">
        <div class="lg:col-span-8 xl:col-span-7">
            <div class="prose prose-slate max-w-none prose-headings:font-extrabold prose-headings:tracking-tight prose-p:leading-8 prose-li:leading-8">
                <div class="not-prose mb-8 border-l-4 border-rose-600 pl-5 text-lg leading-relaxed text-slate-700">
                    {!! $page['description'] !!}
                </div>
                {!! $page['content'] !!}
            </div>
        </div>

        <aside class="lg:col-span-4 lg:col-start-9 xl:col-span-4 xl:col-start-9">
            <div class="sticky top-24 space-y-5 border-t border-slate-200 pt-5">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-700">Overview</p>
                    <div class="mt-3 text-sm leading-7 text-slate-600">
                        {!! $page['description'] !!}
                    </div>
                </div>

                @if ($hasCta)
                    <a href="{{ $page['hero_cta_url'] }}" class="inline-flex w-full items-center justify-center rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        {!! $page['hero_cta_text'] !!}
                    </a>
                @endif
            </div>
        </aside>
    </section>

    @if (!empty($page['content_blocks']))
        <section class="mx-auto max-w-screen-2xl space-y-8 px-5 pb-14 sm:px-8 lg:px-12 2xl:px-20">
            @foreach ($page['content_blocks'] as $block)
                @includeIf('livewire.pages.blocks.' . ($block['type'] ?? ''), ['data' => $block['data'] ?? [], 'page' => $page])
            @endforeach
        </section>
    @endif
</article>
