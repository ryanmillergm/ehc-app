<section data-page-block="cta" class="rounded-2xl border border-rose-200 bg-gradient-to-br from-white to-rose-50 p-5 shadow-sm sm:p-7">
    <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{!! $data['title'] ?? '' !!}</h2>
    @if (!empty($data['body']))
        <p class="mt-3 text-slate-700">{!! $data['body'] !!}</p>
    @endif
    @if (!empty($data['button_text']) && !empty($data['button_url']))
        <a href="{{ $data['button_url'] }}" class="mt-5 inline-flex rounded-full bg-rose-700 px-6 py-3 text-sm font-bold text-white hover:bg-rose-800">{!! $data['button_text'] !!}</a>
    @endif
</section>
