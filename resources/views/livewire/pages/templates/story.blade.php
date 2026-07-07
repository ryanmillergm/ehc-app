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
        '70' => 'min-h-[46vh] md:min-h-[62vh]',
        '100' => 'min-h-[54vh] md:min-h-[82vh]',
        default => 'min-h-[50vh] md:min-h-[70vh]',
    };
    $overlayClass = match ((string) ($page['hero_overlay'] ?? 'medium')) {
        'none' => '',
        'light' => 'bg-slate-950/20',
        'dark' => 'bg-slate-950/60',
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
    $hasCta = !empty($page['hero_cta_text']) && !empty($page['hero_cta_url']);
@endphp

<article data-page-template="story" class="overflow-x-hidden bg-[#fffaf3] text-slate-900" @if($rtl) dir="rtl" @endif>
    <section class="{{ $isFullBleed ? 'relative left-1/2 right-1/2 -mx-[50vw] w-screen' : 'mx-auto max-w-screen-2xl px-5 sm:px-8 lg:px-12 2xl:px-20 pt-8 sm:pt-10' }}">
        <div class="relative overflow-hidden {{ $isFullBleed ? '' : 'rounded-2xl border border-amber-100 shadow-sm' }} {{ $hasMedia ? $heightClass . ' bg-slate-950 text-white' : 'bg-gradient-to-br from-amber-50 via-white to-rose-50' }}">
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

            <div class="relative flex {{ $hasMedia ? $heightClass : 'min-h-[24rem]' }} items-center px-5 py-12 sm:px-8 lg:px-14">
                <div class="min-w-0 w-full {{ $textAlignClass }} {{ $textWidthClass }} space-y-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] {{ $hasMedia ? 'text-white/75' : 'text-rose-700' }}">A ministry story</p>
                    <h1 class="break-words text-3xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                        {!! $page['hero_title'] ?? $page['title'] !!}
                    </h1>
                    @if (!empty($page['hero_subtitle']))
                        <div class="max-w-3xl text-lg leading-8 {{ $hasMedia ? 'text-white/90' : 'text-slate-700' }}">
                            {!! $page['hero_subtitle'] !!}
                        </div>
                    @endif
                    @if ($hasCta)
                        <a href="{{ $page['hero_cta_url'] }}" class="inline-flex items-center justify-center rounded-full {{ $hasMedia ? 'bg-white text-slate-950 hover:bg-slate-100' : 'bg-slate-950 text-white hover:bg-slate-800' }} px-6 py-3 text-sm font-semibold transition">
                            {!! $page['hero_cta_text'] !!}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-screen-2xl px-5 py-12 sm:px-8 sm:py-16 lg:px-12 2xl:px-20">
        <div class="mx-auto max-w-5xl">
            <div class="mb-10 border-y border-amber-200 py-6 text-xl font-medium leading-9 text-slate-700">
                {!! $page['description'] !!}
            </div>

            <div class="prose prose-lg prose-slate max-w-none prose-headings:font-black prose-headings:tracking-tight prose-p:leading-9 prose-li:leading-8">
                {!! $page['content'] !!}
            </div>

            @if ($hasCta)
                <div class="mt-12 border-t border-amber-200 pt-8">
                    <a href="{{ $page['hero_cta_url'] }}" class="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        {!! $page['hero_cta_text'] !!}
                    </a>
                </div>
            @endif
        </div>
    </section>

    @if (!empty($page['content_blocks']))
        <section class="mx-auto max-w-5xl space-y-8 px-5 pb-14 sm:px-8">
            @foreach ($page['content_blocks'] as $block)
                @includeIf('livewire.pages.blocks.' . ($block['type'] ?? ''), ['data' => $block['data'] ?? [], 'page' => $page])
            @endforeach
        </section>
    @endif
</article>
