@php
    $heading = $heading ?? 'Frequently asked questions';
    $subheading = $subheading ?? null;
    $faqItems = collect($faqItems ?? [])->values();
@endphp

@if ($faqItems->isNotEmpty())
    <section id="faq" class="scroll-mt-20 py-16 sm:py-20 bg-white">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="max-w-4xl">
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                    {{ $heading }}
                </h2>
                @if ($subheading)
                    <p class="mt-3 text-lg text-slate-600">
                        {{ $subheading }}
                    </p>
                @endif
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($faqItems as $faq)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="font-bold text-slate-900">{{ $faq->question }}</h3>
                        <p class="mt-2 text-slate-600">{{ $faq->answer }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
