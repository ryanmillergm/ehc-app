@php($items = collect($data['items'] ?? [])->filter(fn ($item) => is_array($item))->values())
@if ($items->isNotEmpty())
    <section data-page-block="pricing">
        <div class="grid gap-4 md:grid-cols-3">
        @foreach ($items as $item)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h3 class="text-lg font-black tracking-tight text-slate-900">{!! $item['name'] ?? '' !!}</h3>
                <p class="mt-2 text-3xl font-black text-rose-700">{!! $item['price'] ?? '' !!}</p>
                @php($features = collect($item['features'] ?? [])->filter(fn ($feature) => is_array($feature) && filled($feature['text'] ?? null))->values())
                @if ($features->isNotEmpty())
                    <ul class="mt-4 space-y-2">
                        @foreach ($features as $feature)
                            <li class="flex gap-2 text-sm text-slate-700">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500"></span>
                                <span>{!! $feature['text'] !!}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
        </div>
    </section>
@endif
