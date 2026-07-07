@php($items = collect($data['items'] ?? [])->filter(fn ($item) => is_array($item))->values())
@if ($items->isNotEmpty())
    <section data-page-block="stats">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($items as $item)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <p class="text-xs uppercase tracking-wider text-slate-500">{!! $item['label'] ?? '' !!}</p>
                <p class="mt-2 text-3xl font-black tracking-tight text-slate-900">{!! $item['value'] ?? '' !!}</p>
            </div>
        @endforeach
        </div>
    </section>
@endif
