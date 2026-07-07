<section data-page-block="faq_teaser" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h3 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{!! $data['title'] ?? '' !!}</h3>
    @if (!empty($data['body']))
        <p class="mt-3 text-slate-700">{!! $data['body'] !!}</p>
    @endif
    @if (!empty($data['button_text']) && !empty($data['button_url']))
        <a href="{{ $data['button_url'] }}" class="mt-5 inline-flex rounded-full border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-slate-50">{!! $data['button_text'] !!}</a>
    @endif
</section>
