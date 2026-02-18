@php
    $theme = $page['theme'] ?? 'default';
    $rtl = (bool) ($page['right_to_left'] ?? false);

    $themeClasses = match ($theme) {
        'warm' => 'bg-rose-50 text-rose-950',
        'slate' => 'bg-slate-50 text-slate-900',
        default => 'bg-white text-slate-900',
    };
@endphp

<article class="space-y-6 rounded-3xl border border-slate-200 p-6 sm:p-8 {{ $themeClasses }}" @if($rtl) dir="rtl" @endif>
    @if (($page['hero_mode'] ?? 'none') !== 'none')
        <section class="space-y-4">
            <h1 class="text-3xl font-extrabold tracking-tight">{{ $page['hero_title'] ?? $page['title'] }}</h1>
            @if (!empty($page['hero_subtitle']))
                <p class="text-lg opacity-80">{{ $page['hero_subtitle'] }}</p>
            @endif

            @if (($page['hero_mode'] ?? null) === 'image' && !empty($page['hero_image']['url']))
                <img
                    src="{{ $page['hero_image']['url'] }}"
                    alt="{{ $page['hero_image']['alt'] ?? $page['title'] }}"
                    class="w-full rounded-2xl object-cover shadow"
                />
            @elseif (($page['hero_mode'] ?? null) === 'video' && !empty($page['hero_video']))
                <x-media.video :video="$page['hero_video']" variant="hero" />
            @elseif (($page['hero_mode'] ?? null) === 'slider' && !empty($page['hero_slides']))
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($page['hero_slides'] as $slide)
                        <img
                            src="{{ $slide['url'] }}"
                            alt="{{ $slide['alt'] ?? $page['title'] }}"
                            class="w-full rounded-2xl object-cover shadow"
                        />
                    @endforeach
                </div>
            @endif

            @if (!empty($page['hero_cta_text']) && !empty($page['hero_cta_url']))
                <a href="{{ $page['hero_cta_url'] }}"
                   class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition">
                    {{ $page['hero_cta_text'] }}
                </a>
            @endif
        </section>
    @endif

    <section class="prose prose-slate max-w-none">
        <h2 class="mb-2">{{ $page['title'] }}</h2>
        <p class="text-slate-600">{{ $page['description'] }}</p>
        <div class="mt-4">{!! $page['content'] !!}</div>
    </section>
</article>
