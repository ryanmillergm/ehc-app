@php
    $blocks = $page['content_blocks'] ?? [];
    $wideBlockTypes = ['gallery', 'stats', 'feature_grid', 'testimonials', 'pricing'];
@endphp

<article class="overflow-x-hidden bg-white text-slate-900" @if(($page['right_to_left'] ?? false)) dir="rtl" @endif>
    @forelse ($blocks as $block)
        @php
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];
            $isHero = $type === 'hero';
            $isWide = in_array($type, $wideBlockTypes, true);
            $containerClass = $isWide
                ? 'mx-auto max-w-screen-2xl px-5 py-5 sm:px-8 sm:py-6 lg:px-12 2xl:px-20'
                : 'mx-auto max-w-5xl px-5 py-5 sm:px-8 sm:py-6';
        @endphp

        @if ($isHero)
            @includeIf('livewire.pages.blocks.' . $type, ['data' => $data, 'page' => $page])
        @else
            <section class="{{ $containerClass }}">
                @includeIf('livewire.pages.blocks.' . $type, ['data' => $data, 'page' => $page])
            </section>
        @endif
    @empty
        <section class="mx-auto max-w-5xl px-5 py-12 sm:px-8 sm:py-16">
            <div class="prose prose-slate max-w-none">
                <h1>{!! $page['title'] !!}</h1>
                <p>{!! $page['description'] !!}</p>
                <div>{!! $page['content'] !!}</div>
            </div>
        </section>
    @endforelse
</article>
