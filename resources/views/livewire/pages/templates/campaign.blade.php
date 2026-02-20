@php
    $rtl = (bool) ($page['right_to_left'] ?? false);
    $layout = $page['layout_data'] ?? [];
    $eyebrow = $layout['eyebrow'] ?? 'Campaign';
    $trustBadges = $layout['trust_badges'] ?? [];
    $impactStats = $layout['impact_stats'] ?? [];
    $quickFacts = $layout['quick_facts'] ?? [];
    $secondaryCtaText = $layout['cta_secondary_text'] ?? null;
    $secondaryCtaUrl = $layout['cta_secondary_url'] ?? null;
    $faqTitle = $layout['faq_teaser_title'] ?? null;
    $faqBody = $layout['faq_teaser_body'] ?? null;
@endphp

<article class="relative overflow-hidden rounded-[2rem] border border-rose-200/80 bg-white shadow-2xl shadow-rose-200/40" @if($rtl) dir="rtl" @endif>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(244,63,94,0.18),_transparent_44%),radial-gradient(circle_at_bottom_left,_rgba(251,191,36,0.16),_transparent_40%)]"></div>
    <div class="relative space-y-10 p-6 sm:p-10 lg:p-12">
        <section class="grid gap-8 lg:grid-cols-12 lg:items-center">
            <div class="space-y-6 lg:col-span-7">
                <div class="inline-flex items-center gap-2 rounded-full bg-rose-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-rose-800">
                    {{ $eyebrow }}
                </div>

                <h1 class="text-4xl font-black leading-tight tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                    {{ $page['hero_title'] ?? $page['title'] }}
                </h1>

                @if (!empty($page['hero_subtitle']))
                    <p class="max-w-2xl text-lg leading-relaxed text-slate-700">
                        {{ $page['hero_subtitle'] }}
                    </p>
                @endif

                @if (!empty($trustBadges))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($trustBadges as $badge)
                            <span class="rounded-full border border-rose-200 bg-white px-3 py-1 text-xs font-semibold text-rose-800">
                                {{ $badge }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    @if (!empty($page['hero_cta_text']) && !empty($page['hero_cta_url']))
                        <a href="{{ $page['hero_cta_url'] }}"
                           class="inline-flex items-center rounded-full bg-rose-700 px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-rose-300/40 hover:bg-rose-800 transition">
                            {{ $page['hero_cta_text'] }}
                        </a>
                    @endif

                    @if (!empty($secondaryCtaText) && !empty($secondaryCtaUrl))
                        <a href="{{ $secondaryCtaUrl }}"
                           class="inline-flex items-center rounded-full border border-slate-300 bg-white px-7 py-3.5 text-sm font-semibold text-slate-900 hover:bg-slate-50 transition">
                            {{ $secondaryCtaText }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="space-y-4 lg:col-span-5">
                @if (($page['hero_mode'] ?? null) === 'video' && !empty($page['hero_video']))
                    <div class="overflow-hidden rounded-3xl shadow-xl ring-1 ring-slate-200">
                        <x-media.video :video="$page['hero_video']" variant="hero" />
                    </div>
                @elseif (($page['hero_mode'] ?? null) === 'slider' && !empty($page['hero_slides']))
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($page['hero_slides'] as $slide)
                            <img src="{{ $slide['url'] }}" alt="{{ $slide['alt'] ?? $page['title'] }}" class="h-40 w-full rounded-2xl object-cover shadow-lg ring-1 ring-slate-200" />
                        @endforeach
                    </div>
                @elseif (!empty($page['hero_image']['url']))
                    <img src="{{ $page['hero_image']['url'] }}" alt="{{ $page['hero_image']['alt'] ?? $page['title'] }}" class="w-full rounded-3xl object-cover shadow-xl ring-1 ring-slate-200" />
                @endif

                @if (!empty($quickFacts))
                    <div class="rounded-2xl border border-rose-200 bg-white/95 p-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-rose-700">Quick Facts</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-700">
                            @foreach ($quickFacts as $fact)
                                <li class="flex items-start gap-2">
                                    <span class="mt-1.5 h-2 w-2 rounded-full bg-rose-500"></span>
                                    <span>{{ $fact }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        @if (!empty($impactStats))
            <section class="grid gap-3 sm:grid-cols-3">
                @foreach ($impactStats as $stat)
                    <div class="rounded-2xl border border-rose-200 bg-white px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-rose-700">{{ $stat['label'] ?? 'Impact' }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-900">{{ $stat['value'] ?? '' }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        <section class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <div class="prose prose-slate max-w-none prose-headings:font-black prose-headings:tracking-tight prose-p:leading-relaxed prose-li:leading-relaxed">
                    <p class="text-slate-700">{{ $page['description'] }}</p>
                    <div class="mt-5">{!! $page['content'] !!}</div>
                </div>
            </div>

            <aside class="lg:col-span-4 space-y-4">
                <div class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-widest text-rose-700">Need clarity?</p>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ $faqTitle ?? 'Have questions before you give?' }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ $faqBody ?? 'Learn how outreach works, where we serve, and how your support is used.' }}</p>
                    <a href="{{ url('/') }}#faq-grid" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition">
                        Read FAQ
                    </a>
                </div>
            </aside>
        </section>
    </div>
</article>
