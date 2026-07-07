@php($items = collect($data['items'] ?? [])->filter(fn ($item) => is_array($item))->values())
@if ($items->isNotEmpty())
    <section data-page-block="testimonials">
        <div class="grid gap-4 md:grid-cols-2">
        @foreach ($items as $item)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-base sm:text-lg font-semibold leading-relaxed text-slate-900">"{!! $item['quote'] ?? '' !!}"</p>
                <p class="mt-4 text-sm font-bold uppercase tracking-wider text-slate-600">{!! $item['name'] ?? '' !!}</p>
            </article>
        @endforeach
        </div>
    </section>
@endif
