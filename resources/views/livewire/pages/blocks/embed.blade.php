@if (!empty($data['url']))
    <section class="space-y-3">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <iframe src="{{ $data['url'] }}" class="h-64 w-full sm:h-[420px]" loading="lazy"></iframe>
        </div>
        @if (!empty($data['caption']))
            <p class="text-sm text-slate-600">{!! $data['caption'] !!}</p>
        @endif
    </section>
@endif
