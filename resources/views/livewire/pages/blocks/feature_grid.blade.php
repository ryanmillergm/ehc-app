@php($items = collect($data['items'] ?? [])->filter(fn ($item) => is_array($item))->values())
@if ($items->isNotEmpty())
    <section data-page-block="feature_grid">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $item)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5">
                <h3 class="text-lg font-black tracking-tight text-slate-900">{!! $item['title'] ?? '' !!}</h3>
                <p class="mt-3 text-slate-700">{!! $item['body'] ?? '' !!}</p>
            </article>
        @endforeach
        </div>
    </section>
@endif
