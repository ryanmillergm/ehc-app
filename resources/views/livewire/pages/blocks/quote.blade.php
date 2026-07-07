<blockquote class="rounded-2xl border border-rose-200 bg-gradient-to-br from-white to-rose-50 p-5 sm:p-6 shadow-sm">
    <p class="text-lg font-bold leading-relaxed text-slate-900">"{!! $data['quote'] ?? '' !!}"</p>
    @if (!empty($data['attribution']))
        <footer class="mt-4 text-sm font-semibold uppercase tracking-wide text-slate-600">- {!! $data['attribution'] !!}</footer>
    @endif
</blockquote>
