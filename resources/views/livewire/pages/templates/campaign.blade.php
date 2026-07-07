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
        '70' => 'min-h-[52vh] md:min-h-[70vh]',
        '100' => $isFullBleed
            ? 'min-h-[calc(100svh-4rem)] md:min-h-[calc(100vh-4rem)]'
            : 'min-h-[calc(100svh-8rem)] sm:min-h-[calc(100svh-9rem)] md:min-h-[calc(100vh-9rem)]',
        default => 'min-h-[56vh] md:min-h-[82vh]',
    };
    $overlayClass = match ((string) ($page['hero_overlay'] ?? 'medium')) {
        'none' => 'bg-gradient-to-r from-slate-950/30 via-slate-950/10 to-transparent',
        'light' => 'bg-gradient-to-r from-slate-950/60 via-slate-950/30 to-transparent',
        'dark' => 'bg-gradient-to-r from-slate-950/90 via-slate-950/60 to-slate-950/20',
        default => 'bg-gradient-to-r from-slate-950/75 via-slate-950/50 to-slate-950/10',
    };
    $textAlignClass = match ((string) ($page['hero_text_align'] ?? 'left')) {
        'center' => 'text-center items-center mx-auto',
        'right' => 'text-right items-end ml-auto',
        default => 'text-left items-start',
    };
    $textWidthClass = match ((string) ($page['hero_text_width'] ?? 'normal')) {
        'narrow' => 'max-w-2xl',
        'wide' => 'max-w-5xl',
        default => 'max-w-3xl',
    };
    $hasCta = !empty($page['hero_cta_text']) && !empty($page['hero_cta_url']);
@endphp

<article data-page-template="campaign" class="overflow-x-hidden bg-white text-slate-900" @if($rtl) dir="rtl" @endif>
    <section class="{{ $isFullBleed ? 'relative left-1/2 right-1/2 -mx-[50vw] w-screen' : 'mx-auto max-w-screen-2xl px-5 py-8 sm:px-8 sm:py-10 lg:px-12 2xl:px-20' }}">
        <div class="relative overflow-hidden {{ $isFullBleed ? '' : 'rounded-2xl border border-slate-200 shadow-lg' }} {{ $heightClass }} bg-slate-950 text-white">
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
                <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-rose-950 to-amber-800"></div>
            @endif

            <div class="absolute inset-0 {{ $overlayClass }}"></div>
            <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-slate-950/70 to-transparent"></div>

            <div class="relative flex {{ $heightClass }} items-center px-5 py-12 sm:px-8 lg:px-14">
                <div class="min-w-0 w-full {{ $textAlignClass }} {{ $textWidthClass }} space-y-6">
                    <div class="inline-flex items-center rounded-full bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-white ring-1 ring-white/20">
                        Bread of Grace Ministries
                    </div>
                    <h1 class="break-words text-3xl font-black leading-[1.05] tracking-tight sm:text-6xl lg:text-7xl">
                        {!! $page['hero_title'] ?? $page['title'] !!}
                    </h1>
                    @if (!empty($page['hero_subtitle']))
                        <div class="max-w-3xl text-lg leading-relaxed text-white/90 sm:text-xl">
                            {!! $page['hero_subtitle'] !!}
                        </div>
                    @endif
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center {{ (string) ($page['hero_text_align'] ?? 'left') === 'center' ? 'sm:justify-center' : ((string) ($page['hero_text_align'] ?? 'left') === 'right' ? 'sm:justify-end' : '') }}">
                        @if ($hasCta)
                            <a href="{{ $page['hero_cta_url'] }}" class="inline-flex items-center justify-center rounded-full bg-rose-600 px-7 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-rose-700">
                                {!! $page['hero_cta_text'] !!}
                            </a>
                        @endif
                        <a href="#campaign-details" class="inline-flex items-center justify-center rounded-full bg-white/10 px-7 py-3.5 text-sm font-bold text-white ring-1 ring-white/25 transition hover:bg-white/20">
                            View details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="campaign-details" class="mx-auto grid max-w-screen-2xl gap-10 px-5 py-12 sm:px-8 sm:py-16 lg:grid-cols-12 lg:px-12 2xl:px-20">
        <div class="lg:col-span-7 xl:col-span-8">
            <div class="mb-8 border-b border-slate-200 pb-7">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-700">Why it matters now</p>
                <div class="mt-3 max-w-4xl text-xl font-semibold leading-8 text-slate-900">
                    {!! $page['description'] !!}
                </div>
            </div>

            <div class="prose prose-slate max-w-none prose-headings:font-extrabold prose-headings:tracking-tight prose-p:leading-8 prose-li:leading-8">
                {!! $page['content'] !!}
            </div>
        </div>

        <aside class="lg:col-span-5 xl:col-span-4">
            <div class="sticky top-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
                <div class="bg-slate-950 px-6 py-5 text-white">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-200">Take action</p>
                    <div class="mt-2 text-2xl font-black tracking-tight">
                        {!! $page['hero_title'] ?? $page['title'] !!}
                    </div>
                </div>
                <div class="space-y-5 p-6">
                    <div class="text-sm leading-7 text-slate-600">
                        {!! $page['description'] !!}
                    </div>
                    @if ($hasCta)
                        <a href="{{ $page['hero_cta_url'] }}" class="inline-flex w-full items-center justify-center rounded-full bg-rose-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-rose-700">
                            {!! $page['hero_cta_text'] !!}
                        </a>
                    @endif
                </div>
            </div>
        </aside>
    </section>

    @if (!empty($page['content_blocks']))
        <section class="border-y border-slate-200 bg-slate-50">
            <div class="mx-auto max-w-screen-2xl space-y-8 px-5 py-12 sm:px-8 lg:px-12 2xl:px-20">
                @foreach ($page['content_blocks'] as $block)
                    @includeIf('livewire.pages.blocks.' . ($block['type'] ?? ''), ['data' => $block['data'] ?? [], 'page' => $page])
                @endforeach
            </div>
        </section>
    @endif
</article>
