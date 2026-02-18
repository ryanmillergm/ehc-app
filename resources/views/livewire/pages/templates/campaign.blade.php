@php
    $rtl = (bool) ($page['right_to_left'] ?? false);
@endphp

<article class="space-y-8 rounded-3xl border border-rose-200 bg-gradient-to-b from-rose-50 to-white p-6 sm:p-8" @if($rtl) dir="rtl" @endif>
    <section class="space-y-4">
        <div class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">
            Campaign
        </div>
        <h1 class="text-4xl font-extrabold tracking-tight text-slate-900">{{ $page['hero_title'] ?? $page['title'] }}</h1>
        @if (!empty($page['hero_subtitle']))
            <p class="max-w-3xl text-lg text-slate-700">{{ $page['hero_subtitle'] }}</p>
        @endif

        @if (($page['hero_mode'] ?? null) === 'video' && !empty($page['hero_video']))
            <x-media.video :video="$page['hero_video']" variant="hero" />
        @elseif (($page['hero_mode'] ?? null) === 'slider' && !empty($page['hero_slides']))
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($page['hero_slides'] as $slide)
                    <img src="{{ $slide['url'] }}" alt="{{ $slide['alt'] ?? $page['title'] }}" class="h-56 w-full rounded-2xl object-cover shadow" />
                @endforeach
            </div>
        @elseif (!empty($page['hero_image']['url']))
            <img src="{{ $page['hero_image']['url'] }}" alt="{{ $page['hero_image']['alt'] ?? $page['title'] }}" class="w-full rounded-2xl object-cover shadow" />
        @endif

        @if (!empty($page['hero_cta_text']) && !empty($page['hero_cta_url']))
            <a href="{{ $page['hero_cta_url'] }}"
               class="inline-flex items-center rounded-full bg-rose-700 px-6 py-3 text-sm font-bold text-white hover:bg-rose-800 transition">
                {{ $page['hero_cta_text'] }}
            </a>
        @endif
    </section>

    <section class="prose prose-slate max-w-none">
        <p class="text-slate-700">{{ $page['description'] }}</p>
        <div class="mt-4">{!! $page['content'] !!}</div>
    </section>
</article>
