@php($items = collect($data['items'] ?? [])->filter(fn ($item) => is_array($item) && filled($item['text'] ?? null))->values())
@if ($items->isNotEmpty())
    <ul class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
        @foreach ($items as $item)
            <li class="flex items-start gap-2 text-slate-700">
                <span class="mt-2 inline-block h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                <span class="leading-relaxed">{!! $item['text'] !!}</span>
            </li>
        @endforeach
    </ul>
@endif
