@if (!empty($data['src']))
    <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <img src="{{ $data['src'] }}" alt="{{ $data['alt'] ?? $page['title'] }}" class="w-full object-cover" />
        @if (!empty($data['caption']))
            <figcaption class="border-t border-slate-200 px-5 py-3 text-sm text-slate-600">{!! $data['caption'] !!}</figcaption>
        @endif
    </figure>
@endif
