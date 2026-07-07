@php($items = collect($data['items'] ?? [])->filter(fn ($item) => is_array($item))->values())
@if ($items->isNotEmpty())
    <section data-page-block="timeline" class="space-y-4">
        @foreach ($items as $item)
            <div class="relative rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <span class="absolute left-0 top-0 h-full w-1 rounded-l-2xl bg-rose-500"></span>
                <h3 class="text-lg font-black tracking-tight text-slate-900">{!! $item['title'] ?? '' !!}</h3>
                <p class="mt-2 text-slate-700">{!! $item['body'] ?? '' !!}</p>
            </div>
        @endforeach
    </section>
@endif
