@php
    $rtl = (bool) ($page['right_to_left'] ?? false);
    $layout = $page['layout_data'] ?? [];
    $eyebrow = $layout['eyebrow'] ?? 'Story';
    $trustBadges = $layout['trust_badges'] ?? [];
    $secondaryCtaText = $layout['cta_secondary_text'] ?? null;
    $secondaryCtaUrl = $layout['cta_secondary_url'] ?? null;
@endphp

<article class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/60" @if($rtl) dir="rtl" @endif>
    <div class="absolute inset-0 bg-gradient-to-br from-white via-slate-50 to-amber-50/40"></div>
    <div class="relative space-y-10 p-6 sm:p-10 lg:p-12">
        <section class="grid gap-8 lg:grid-cols-12 lg:items-center">
            <div class="space-y-6 lg:col-span-7">
                <div class="inline-flex items-center rounded-full bg-slate-100 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-slate-700">
                    {{ $eyebrow }}
                </div>

                <h1 class="text-4xl font-black leading-tight tracking-tight text-slate-900 sm:text-5xl">
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
                            <span class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ $badge }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    @if (!empty($page['hero_cta_text']) && !empty($page['hero_cta_url']))
                        <a href="{{ $page['hero_cta_url'] }}"
                           class="inline-flex items-center rounded-full bg-slate-900 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800 transition">
                            {{ $page['hero_cta_text'] }}
                        </a>
                    @endif

                    @if (!empty($secondaryCtaText) && !empty($secondaryCtaUrl))
                        <a href="{{ $secondaryCtaUrl }}"
                           class="inline-flex items-center rounded-full border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50 transition">
                            {{ $secondaryCtaText }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="lg:col-span-5">
                @if (($page['hero_mode'] ?? null) === 'video' && !empty($page['hero_video']))
                    <div class="overflow-hidden rounded-3xl shadow-xl ring-1 ring-slate-200">
                        <x-media.video :video="$page['hero_video']" variant="hero" />
                    </div>
                @elseif (($page['hero_mode'] ?? null) === 'slider' && !empty($page['hero_slides']))
                    @php($firstSlide = $page['hero_slides'][0] ?? null)
                    @if ($firstSlide)
                        <img src="{{ $firstSlide['url'] }}" alt="{{ $firstSlide['alt'] ?? $page['title'] }}" class="w-full rounded-3xl object-cover shadow-xl ring-1 ring-slate-200" />
                    @endif
                @elseif (!empty($page['hero_image']['url']))
                    <img src="{{ $page['hero_image']['url'] }}" alt="{{ $page['hero_image']['alt'] ?? $page['title'] }}" class="w-full rounded-3xl object-cover shadow-xl ring-1 ring-slate-200" />
                @endif
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <div class="prose prose-slate max-w-none prose-headings:font-black prose-headings:tracking-tight prose-p:leading-relaxed prose-li:leading-relaxed">
                    <p class="text-slate-600">{{ $page['description'] }}</p>
                    <div class="mt-5">{!! $page['content'] !!}</div>
                </div>
            </div>

            <aside class="lg:col-span-4">
                <blockquote class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-widest text-slate-500">Why This Matters</p>
                    <p class="mt-3 text-lg font-semibold leading-relaxed text-slate-900">
                        “Consistent presence is often the first step toward restored hope.”
                    </p>
                </blockquote>
            </aside>
        </section>
    </div>
</article>
