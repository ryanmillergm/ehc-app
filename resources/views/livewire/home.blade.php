<div class="bg-white text-slate-900">

    {{-- HERO (donation-first) --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>

        <div class="relative mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 pt-16 lg:pt-24 pb-16">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

                {{-- Copy --}}
                <div class="lg:col-span-6 space-y-7">
                    <div class="inline-flex items-center gap-2 rounded-full bg-rose-50 text-rose-700 px-4 py-1.5 text-sm font-semibold">
                        Bread of Grace Ministries
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-600"></span>
                        Sacramento Outreach
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.05]">
                        Your gift helps someone
                        <span class="text-rose-700">eat today</span> and
                        <span class="text-rose-700">start again tomorrow</span>.
                    </h1>

                    <p class="text-lg sm:text-xl text-slate-600 leading-relaxed max-w-2xl">
                        We serve the homeless and at-risk community with hot meals, survival supplies,
                        mentorship, and a path toward housing and employment — all in the name of Jesus.
                    </p>

                    {{-- Quick impact chips --}}
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">$25 = hot meals</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">$50 = supplies + care</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">$100 = discipleship support</span>
                    </div>

                    {{-- CTA --}}
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="#donate"
                           class="inline-flex items-center justify-center rounded-full bg-rose-700 px-7 py-3.5
                                  text-base font-semibold text-white shadow-sm hover:bg-rose-800 transition
                                  focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                            Give now
                        </a>

                        <a href="#story"
                           class="inline-flex items-center justify-center rounded-full border border-slate-300 px-7 py-3.5
                                  text-base font-semibold text-slate-800 hover:bg-white transition">
                            See the impact
                        </a>
                    </div>

                    {{-- Trust row --}}
                    <div class="pt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-500">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                            Secure giving
                        </div>
                        <div>Tax-deductible</div>
                        <div>Cancel recurring anytime</div>
                    </div>
                </div>

                {{-- Hero image --}}
                <div class="lg:col-span-6">
                    <div class="relative">
                        <div class="absolute -inset-6 bg-gradient-to-tr from-rose-200/40 via-white to-sky-200/40 blur-2xl"></div>
                        <img
                            src="{{ asset('images/the-mayor.jpeg') }}"
                            alt="Bread of Grace outreach"
                            class="relative w-full rounded-3xl object-cover shadow-2xl ring-1 ring-slate-200"
                            loading="eager"
                        />
                    </div>
                </div>

            </div>
        </div>
    </section>


    {{-- IMPACT STATS --}}
    <section class="py-10 bg-white border-y border-slate-100">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
                <div class="rounded-2xl bg-slate-50 p-6">
                    <div class="text-4xl font-extrabold text-slate-900">Weekly</div>
                    <div class="mt-2 text-slate-600">Street outreach + church service</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-6">
                    <div class="text-4xl font-extrabold text-slate-900">Meals</div>
                    <div class="mt-2 text-slate-600">Food + supplies distributed regularly</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-6">
                    <div class="text-4xl font-extrabold text-slate-900">Mentorship</div>
                    <div class="mt-2 text-slate-600">Discipleship + life coaching</div>
                </div>
            </div>
        </div>
    </section>


    {{-- STORY / WHY GIVE --}}
    <section id="story" class="py-16 sm:py-20 bg-slate-50">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

                <div class="lg:col-span-6">
                    <img
                        src="{{ asset('images/lisa-hug.jpeg') }}"
                        alt="Your giving changes lives"
                        class="w-full rounded-3xl object-cover shadow-xl ring-1 ring-slate-200 bg-white"
                        loading="lazy"
                    />
                </div>

                <div class="lg:col-span-6 space-y-6">
                    <h2 class="text-3xl sm:text-4xl font-bold leading-tight">
                        Giving that meets real needs — and points to real hope
                    </h2>

                    <p class="text-lg text-slate-600 leading-relaxed">
                        Every week, we show up where people are hurting. We bring food, supplies, prayer,
                        and consistent mentorship to help people rebuild their lives.
                    </p>

                    <div class="space-y-4">
                        <div class="flex gap-3">
                            <div class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-700"></div>
                            <p class="text-slate-700 text-base leading-relaxed">
                                Your gift provides <span class="font-semibold">hot meals</span> and survival
                                supplies for our outreach.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <div class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-700"></div>
                            <p class="text-slate-700 text-base leading-relaxed">
                                You help fund <span class="font-semibold">mentorship, discipleship, and coaching</span>.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <div class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-700"></div>
                            <p class="text-slate-700 text-base leading-relaxed">
                                You support our long-term vision:
                                <span class="font-semibold">rehabilitation → training → permanent housing</span>.
                            </p>
                        </div>
                    </div>

                    <a href="#donate"
                       class="inline-flex items-center justify-center rounded-full bg-rose-700 px-7 py-3
                              text-sm font-semibold text-white shadow-sm hover:bg-rose-800 transition">
                        I want to help
                    </a>
                </div>

            </div>
        </div>
    </section>


    {{-- DONATION WIDGET EMBED --}}
    <section id="donate" class="py-16 sm:py-20 bg-white">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">

                <div class="lg:col-span-5 space-y-4">
                    <h2 class="text-3xl sm:text-4xl font-bold">Give today</h2>
                    <p class="text-lg text-slate-600 leading-relaxed">
                        Choose an amount and frequency below. Recurring gifts help us stay consistent week to week.
                    </p>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-slate-700">
                        <p class="font-semibold">What happens next?</p>
                        <p class="mt-2 text-sm leading-relaxed">
                            You’ll receive an emailed receipt. Monthly gifts can be changed or canceled anytime.
                        </p>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="rounded-3xl border border-slate-200 bg-white p-4 sm:p-6 shadow-sm">
                        {{-- If your widget uses JS, this prevents Livewire diffing --}}
                        <div wire:ignore>
                            <x-donation-widget />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>


    {{-- TESTIMONIALS (trust + social proof) --}}
    <section class="py-16 sm:py-20 bg-slate-50 border-y border-slate-100">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-10">
                Lives are changing
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <p class="text-slate-700 leading-relaxed">
                        “They didn’t just feed me — they kept showing up, praying with me,
                        and helping me believe I could be whole again.”
                    </p>
                    <p class="mt-4 text-sm font-semibold text-slate-900">— Outreach participant</p>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <p class="text-slate-700 leading-relaxed">
                        “Bread of Grace is the real deal. The love of Christ is visible
                        in everything they do.”
                    </p>
                    <p class="mt-4 text-sm font-semibold text-slate-900">— Local volunteer</p>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <p class="text-slate-700 leading-relaxed">
                        “My regular giving feels personal here. I know exactly what it’s supporting.”
                    </p>
                    <p class="mt-4 text-sm font-semibold text-slate-900">— Monthly donor</p>
                </div>
            </div>
        </div>
    </section>


    {{-- FAQ --}}
    <section class="py-16 sm:py-20 bg-white">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="max-w-3xl mx-auto">
                <h2 class="text-3xl sm:text-4xl font-bold text-center mb-8">
                    Frequently asked questions
                </h2>

                <div class="space-y-4">
                    <details class="group rounded-2xl border border-slate-200 p-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-slate-900">
                            Is my donation tax-deductible?
                            <span class="ml-4 transition group-open:rotate-180">▾</span>
                        </summary>
                        <p class="mt-3 text-slate-600 leading-relaxed">
                            Yes. You’ll automatically receive a receipt by email for your records.
                        </p>
                    </details>

                    <details class="group rounded-2xl border border-slate-200 p-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-slate-900">
                            Can I cancel a recurring gift?
                            <span class="ml-4 transition group-open:rotate-180">▾</span>
                        </summary>
                        <p class="mt-3 text-slate-600 leading-relaxed">
                            Absolutely. You can cancel or change your monthly gift anytime.
                        </p>
                    </details>

                    <details class="group rounded-2xl border border-slate-200 p-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-slate-900">
                            Is giving secure?
                            <span class="ml-4 transition group-open:rotate-180">▾</span>
                        </summary>
                        <p class="mt-3 text-slate-600 leading-relaxed">
                            Yes. Your payment is processed through a secure, encrypted provider.
                        </p>
                    </details>
                </div>
            </div>
        </div>
    </section>


    {{-- FINAL CTA --}}
    <section class="bg-rose-800">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 py-16 sm:py-20 text-center space-y-6">
            <h2 class="text-white text-3xl sm:text-4xl font-extrabold">
                Help us keep showing up every week
            </h2>

            <p class="text-white/90 text-lg max-w-3xl mx-auto leading-relaxed">
                “And whoever gives one of these little ones only a cup of cold water in the name of a disciple,
                assuredly, I say to you, he shall by no means lose his reward.” — Matthew 10:42
            </p>

            <a href="#donate"
               class="inline-flex items-center justify-center rounded-full bg-white px-8 py-3.5
                      text-base font-semibold text-rose-800 shadow-sm hover:bg-slate-100 transition">
                Give now
            </a>
        </div>
    </section>

</div>
