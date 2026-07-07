@php
    $items = collect($data['items'] ?? [])
        ->filter(fn ($item) => is_array($item))
        ->values();
    $imageIds = $items
        ->pluck('image_id')
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();
    $images = $imageIds->isNotEmpty()
        ? \App\Models\Image::query()->whereIn('id', $imageIds)->get()->keyBy('id')
        : collect();
@endphp

@if ($items->isNotEmpty())
    <section data-page-block="gallery">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                @php
                    $image = !empty($item['image_id']) ? $images->get((int) $item['image_id']) : null;
                    $src = ($item['source_type'] ?? 'existing') === 'url'
                        ? ($item['src'] ?? null)
                        : $image?->resolvedUrl();
                    $alt = $item['alt'] ?? $image?->alt_text ?? $page['title'];
                    $caption = $item['caption'] ?? $image?->caption ?? null;
                @endphp

                @if ($src)
                    <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <img src="{{ $src }}" alt="{{ $alt }}" class="h-56 w-full object-cover" />

                        @if (!empty($caption))
                            <figcaption class="border-t border-slate-200 px-4 py-3 text-sm text-slate-600">{!! $caption !!}</figcaption>
                        @endif
                    </figure>
                @endif
            @endforeach
        </div>
    </section>
@endif
