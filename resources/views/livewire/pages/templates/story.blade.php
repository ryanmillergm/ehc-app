@php
    $rtl = (bool) ($page['right_to_left'] ?? false);
@endphp

<article class="space-y-8 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" @if($rtl) dir="rtl" @endif>
    <section class="grid gap-6 lg:grid-cols-12 lg:items-center">
        <div class="lg:col-span-7 space-y-4">
            <div class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Story</div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">{{ $page['hero_title'] ?? $page['title'] }}</h1>
            @if (!empty($page['hero_subtitle']))
                <p class="text-lg text-slate-600">{{ $page['hero_subtitle'] }}</p>
            @endif
            @if (!empty($page['hero_cta_text']) && !empty($page['hero_cta_url']))
                <a href="{{ $page['hero_cta_url'] }}"
                   class="inline-flex items-center rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50 transition">
                    {{ $page['hero_cta_text'] }}
                </a>
            @endif
        </div>

        <div class="lg:col-span-5">
            @if (($page['hero_mode'] ?? null) === 'video' && !empty($page['hero_video']))
                <x-media.video :video="$page['hero_video']" variant="hero" />
            @elseif (($page['hero_mode'] ?? null) === 'slider' && !empty($page['hero_slides']))
                @php($firstSlide = $page['hero_slides'][0] ?? null)
                @if ($firstSlide)
                    <img src="{{ $firstSlide['url'] }}" alt="{{ $firstSlide['alt'] ?? $page['title'] }}" class="w-full rounded-2xl object-cover shadow" />
                @endif
            @elseif (!empty($page['hero_image']['url']))
                <img src="{{ $page['hero_image']['url'] }}" alt="{{ $page['hero_image']['alt'] ?? $page['title'] }}" class="w-full rounded-2xl object-cover shadow" />
            @endif
        </div>
    </section>

    <section class="prose prose-slate max-w-none">
        <p class="text-slate-600">{{ $page['description'] }}</p>
        <div class="mt-4">{!! $page['content'] !!}</div>
    </section>
</article>
