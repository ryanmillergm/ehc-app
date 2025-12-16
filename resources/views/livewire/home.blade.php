<div class="bg-white text-slate-900">
    {{-- HERO --}}
    <section id="hero" class="relative overflow-hidden">
        {{-- Background --}}
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>
            <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-rose-200/40 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 pt-12 lg:pt-20 pb-16 lg:pb-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

                <div class="lg:col-span-6 space-y-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 text-white px-4 py-1.5 text-xs font-semibold tracking-wide">
                            Bread of Grace Ministries
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                            Sacramento, CA
                        </span>
                        <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 px-4 py-1.5 text-xs font-semibold">
                            Serving since 2010
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-4 py-1.5 text-xs font-semibold">
                            Church Without Walls • Thu + Sun 11am
                        </span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.05]">
                        Help restore lives through
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-500 to-rose-700">
                            God’s Word
                        </span>
                        and practical support.
                    </h1>

                    <p class="text-lg sm:text-xl text-slate-600 leading-relaxed max-w-2xl">
                        We reach and serve the homeless and at-risk community with the Bible, hot meals, survival supplies,
                        mentorship/discipleship, employment support, and pathways to housing.
                    </p>

                    {{-- Image --}}
                    <div class="md:hidden">
                        <div class="relative">
                            <div class="absolute -inset-6 bg-gradient-to-tr from-rose-200/40 via-white to-sky-200/40 blur-2xl"></div>
                            <img
                                src="{{ asset('images/sm/the-mayor.jpg') }}"
                                alt="Bread of Grace outreach"
                                class="relative w-full rounded-2xl object-cover shadow-xl ring-1 ring-slate-200"
                                loading="eager"
                            />
                        </div>
                    </div>

                    {{-- Primary CTAs --}}
                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                        <a href="#give-form"
                           class="inline-flex items-center justify-center rounded-full bg-rose-700 px-7 py-3.5
                                  text-lg font-semibold text-white shadow-sm hover:bg-rose-800
                                  focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition">
                            Give today
                            <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10.75 5.75a.75.75 0 0 0-1.5 0V9.25H5.75a.75.75 0 0 0 0 1.5H9.25v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5V5.75Z" />
                            </svg>
                        </a>

                        <a href="#serve"
                           class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-7 py-3.5
                                  text-lg font-semibold text-slate-800 hover:bg-slate-50 transition">
                            Volunteer with us
                        </a>

                        <a href="#visit"
                           class="inline-flex items-center justify-center rounded-full text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
                            Visit Thursday/Sunday →
                        </a>
                    </div>

                    {{-- “Quick choices” cards --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                        <a href="#give-form" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition">
                            <p class="text-md font-semibold text-rose-700">Give</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">Fuel outreach</p>
                            <p class="mt-1 text-sm text-slate-500">Meals, supplies, mentorship</p>
                        </a>
                        <a href="#serve" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition">
                            <p class="text-md font-semibold text-rose-700">Serve</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">Join the team</p>
                            <p class="mt-1 text-sm text-slate-500">Hands + hearts welcome</p>
                        </a>
                        <a href="#about" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition">
                            <p class="text-md font-semibold text-rose-700">Learn</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">How it works</p>
                            <p class="mt-1 text-sm text-slate-500">Our 3-phase pathway</p>
                        </a>
                    </div>
                </div>

                {{-- Image / collage --}}
                <div class="lg:col-span-6">
                    <div class="relative">
                        <div class="absolute -inset-6 bg-gradient-to-tr from-rose-200/50 via-white to-indigo-200/40 blur-2xl"></div>

                        <div class="relative hidden md:grid md:grid-cols-12 md:gap-4 ">
                            {{-- Big image Left stack --}}
                            <div class="md:col-span-7 block">
                                <img
                                    src="{{ asset('images/sm/the-mayor.jpg') }}"
                                    alt="Bread of Grace outreach"
                                    class="w-full aspect-[16/11] md:aspect-auto md:h-[420px] rounded-3xl object-cover shadow-xl ring-1 ring-slate-200"
                                    loading="lazy"
                                />
                            </div>

                            {{-- Right stack --}}
                            <div class="md:col-span-5 space-y-4">
                                <img
                                    src="{{ asset('images/sm/lisa-hug.jpg') }}"
                                    alt="Love and support"
                                    class="w-full aspect-[16/10] md:aspect-auto md:h-[200px] rounded-3xl object-cover shadow-lg ring-1 ring-slate-200"
                                    loading="lazy"
                                />

                                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-xs font-semibold text-slate-600">Scripture that shapes our work</p>
                                    <p class="mt-2 text-sm text-slate-800 leading-relaxed italic">
                                        “And whoever gives one of these little ones only a cup of cold water… shall by no means lose his reward.”
                                    </p>
                                    <p class="mt-2 text-xs font-semibold text-slate-900">— Matthew 10:42</p>
                                </div>
                            </div>
                        </div>

                        {{-- Floating “next meetup” pill --}}
                        <div class="pointer-events-none absolute -bottom-8 sm:-bottom-5 lg:-bottom-20 left-6 right-6">
                            <div class="mx-auto max-w-xl rounded-full bg-slate-900/90 text-white px-5 py-3 shadow-lg ring-1 ring-white/10 backdrop-blur">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs sm:text-sm">
                                    <span class="font-semibold">Meet us: Thursday + Sunday • 11:00am</span>
                                    <span class="text-white/80">Township 9 Park • Sacramento</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>


    {{-- IMPACT STATS --}}
    <section class="hidden md:block bg-slate-950 text-white border-y border-white/10">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 py-10">
            <div class="grid grid-cols-3 gap-6 text-center">
                <div class="rounded-2xl bg-white/5 border border-slate-200/20 ring-1 ring-white/10 p-6">
                    <div class="text-3xl lg:text-4xl font-extrabold text-white">Weekly</div>
                    <div class="mt-2 text-white/75">Street outreach + church service</div>
                </div>

                <div class="rounded-2xl bg-white/5 border border-slate-200/20 ring-1 ring-white/10 p-6">
                    <div class="text-3xl lg:text-4xl font-extrabold text-white">Meals</div>
                    <div class="mt-2 text-white/75">Food + supplies distributed regularly</div>
                </div>

                <div class="rounded-2xl bg-white/5 border border-slate-200/20 ring-1 ring-white/10 p-6">
                    <div class="text-3xl lg:text-4xl font-extrabold text-white">Mentorship</div>
                    <div class="mt-2 text-white/75">Discipleship + life coaching</div>
                </div>
            </div>
        </div>
    </section>



    {{-- ABOUT / HOW IT WORKS --}}
    <section id="about" class="scroll-mt-20 py-16 sm:py-20">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

                <div class="lg:col-span-5 space-y-5">
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                        A simple path to restoration.
                    </h2>
                    <p class="text-lg text-slate-600 leading-relaxed">
                        We believe transformation is spiritual <span class="font-semibold text-slate-800">and</span> practical.
                        So we combine consistent discipleship with tangible steps that rebuild stability and dignity.
                    </p>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-sm font-semibold text-slate-900">What you’ll see in our outreach</p>
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-slate-700">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-rose-600"></span>
                                Bible teaching + prayer
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-rose-600"></span>
                                Hot meals + supplies
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-rose-600"></span>
                                Mentorship + coaching
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-rose-600"></span>
                                Job/housing direction
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-6 sm:px-8 py-6 border-b border-slate-200 bg-gradient-to-r from-rose-50 to-indigo-50">
                            <h3 class="text-xl sm:text-3xl sm:font-extrabold font-extrabold">3 phases to rehabilitation</h3>
                            <p class="mt-1 text-slate-600 font-medium">Built for real life: spiritual foundation + next practical step.</p>
                        </div>

                        <ol class="p-6 sm:p-8 space-y-4">
                            @php
                                $phases = [
                                    ['01', 'Rehabilitation + community housing', 'Counseling, mentorship, discipleship, and stabilization.'],
                                    ['02', 'Education + job training', 'Skills, readiness, and ongoing Christ-centered coaching.'],
                                    ['03', 'Permanent housing + career placement', 'Long-term stability with continued community support.'],
                                ];
                            @endphp

                            @foreach ($phases as [$num, $title, $desc])
                                <li class="group flex gap-4 rounded-2xl border border-slate-200 p-5 hover:shadow-md transition">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-900 text-white font-extrabold">
                                        {{ $num }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ $title }}</div>
                                        <div class="mt-1 text-slate-600">{{ $desc }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- PARALLAX BAND --}}
    <section class="bg-white">
        <div
            class="flex items-center justify-center flex-col py-24 md:py-32 bg-cover bg-center bg-no-repeat bg-fixed"
            style="
                background-image:
                    linear-gradient(0deg, rgba(2,6,23,0.78), rgba(2,6,23,0.45)),
                    url('{{ asset('images/sm/bible-scriptures.jpg') }}');
            "
        >
            <div class="w-full max-w-screen-2xl px-6 lg:px-12 2xl:px-20 text-center">
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-5 py-2 text-white/90 text-xs font-semibold tracking-widest uppercase ring-1 ring-white/15">
                    Mentoring • Coaching • Discipleship
                </div>

                <h2 class="mt-5 text-white text-3xl md:text-5xl font-extrabold tracking-tight">
                    Nobody rebuilds alone.
                </h2>

                <p class="mt-4 text-white/90 max-w-3xl mx-auto leading-relaxed text-lg">
                    We walk alongside people with consistent spiritual guidance, practical life coaching,
                    and Christ-centered community — helping restore identity, purpose, and momentum.
                </p>
            </div>
        </div>
    </section>

    {{-- SERVE (strong CTA section that isn’t just “Donate”) --}}
    <section id="serve" class="scroll-mt-20 py-16 sm:py-20 bg-slate-50 border-y border-slate-200">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

                <div class="lg:col-span-7">
                    <div class="relative overflow-hidden rounded-3xl border border-slate-200 shadow-sm min-h-[400px]">
                        <div
                            class="absolute inset-0 bg-cover bg-center"
                            style="background-image: url('{{ asset('images/sm/bike-path-road.jpg') }}');"
                        ></div>

                        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-950/60 to-slate-950/30"></div>
                        <div class="absolute inset-0 bg-black/10"></div>

                        {{-- Content (vertically centered) --}}
                        <div class="relative flex h-full min-h-[400px] items-center p-7 sm:p-10 lg:p-12">
                            <div class="w-full">
                                <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-xs font-semibold tracking-wide text-white ring-1 ring-white/20">
                                    Serve • Outreach Team
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                                    Sacramento
                                </div>

                                <h2 class="mt-4 text-3xl sm:text-4xl font-extrabold tracking-tight text-white drop-shadow">
                                    Serve with Bread of Grace.
                                </h2>

                                <p class="mt-3 text-lg leading-relaxed text-white/95 max-w-2xl drop-shadow">
                                    Some people give. Some people show up. Some do both.
                                    There’s a place for you — prayer, food service, conversations, discipleship, logistics.
                                </p>

                                <div class="mt-6 flex flex-col sm:flex-row gap-3">
                                    <a href="#give-form"
                                       class="inline-flex items-center justify-center rounded-full bg-rose-600 px-7 py-3.5
                                              text-sm font-semibold text-white shadow-sm hover:bg-rose-700 transition">
                                        Support the work
                                    </a>

                                    <a href="#visit"
                                       class="inline-flex items-center justify-center rounded-full bg-white/10 px-7 py-3.5
                                              text-sm font-semibold text-white ring-1 ring-white/20 hover:bg-white/15 transition">
                                        Come this Thursday/Sunday
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
                        <div class="text-sm font-semibold text-slate-900">A few “easy yes” ways to help</div>
                        <ul class="mt-4 space-y-3 text-slate-700">
                            <li class="flex gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                Bring water / hygiene kits / socks
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                Help serve meals + cleanup
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                Prayer + conversation + encouragement
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                Mentorship / discipleship follow-up
                            </li>
                        </ul>
                        <div class="mt-6 rounded-2xl bg-rose-50 border border-rose-100 p-4">
                            <p class="text-sm text-rose-900">
                                <span class="font-semibold">Pro tip:</span> People remember warmth and consistency more than speeches.
                                Just showing up matters.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- SECTION BREAK (makes SERVE -> GIVE feel intentional, not “same again”) --}}
    <section aria-hidden="true" class="bg-slate-100 border-y border-slate-200">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 py-10">
            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-rose-200/30 blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-indigo-200/30 blur-3xl"></div>

                <div class="relative px-6 py-7 sm:px-10 sm:py-8 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div>
                        <div class="text-xs font-semibold tracking-widest uppercase text-rose-700">
                            Next step
                        </div>
                        <div class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">
                            Ready to make a real difference today?
                        </div>
                        <p class="mt-2 text-slate-600 max-w-2xl">
                            Your gift helps meals, supplies, and consistent discipleship happen every week.
                        </p>
                    </div>

                    <a href="#give-form"
                       class="shrink-0 inline-flex items-center justify-center rounded-full bg-rose-700 px-7 py-3.5
                              text-sm font-semibold text-white shadow-sm hover:bg-rose-800 transition">
                        Jump to donation form →
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- GIVE (two-column with sticky donation widget) --}}
    <section id="give" class="scroll-mt-20 py-16 sm:py-20 bg-gradient-to-b from-white to-slate-50">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start lg:items-stretch">

                <div class="lg:col-span-6 space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-full bg-rose-50 text-rose-700 px-4 py-1.5 text-xs font-semibold">
                        Give • One-time or Monthly
                    </div>

                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                        Make outreach possible this week.
                    </h2>

                    <p class="text-lg text-slate-600 leading-relaxed max-w-2xl">
                        Your gift supports meals, survival supplies, discipleship, and practical next steps toward stability.
                        Monthly giving helps us plan with confidence.
                    </p>

                    <div class="relative h-[600px] sm:h-[400px] overflow-hidden rounded-3xl text-white p-6 sm:p-8">
                        {{-- Background image --}}
                        <div
                            class="absolute inset-0 bg-cover bg-center"
                            style="background-image: url('{{ asset('images/sm/group-joseph-peace.jpg') }}');"
                        ></div>

                        {{-- Overlay for readability --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/70 via-slate-950/45 to-slate-950/20"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-black/0 to-black/0"></div>

                        {{-- Content --}}
                        <div class="relative flex h-full flex-col">
                            <div class="mt-auto">
                                <p class="text-sm font-semibold text-white/85 drop-shadow">Our heart</p>

                                <p class="mt-3 text-lg leading-relaxed italic text-white/95 drop-shadow">
                                    “For I was hungry and you gave Me food; I was thirsty and you gave Me drink; I was a stranger and you took Me in…”
                                </p>

                                <p class="mt-3 text-sm font-semibold text-white/90 drop-shadow">— Matthew 25:35</p>

                                <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                                    <div class="rounded-2xl bg-white/12 backdrop-blur-md ring-1 ring-white/20 p-4">
                                        <div class="font-extrabold">Meals</div>
                                        <div class="text-white/85">Hot food served with dignity</div>
                                    </div>

                                    <div class="rounded-2xl bg-white/12 backdrop-blur-md ring-1 ring-white/20 p-4">
                                        <div class="font-extrabold">Supplies</div>
                                        <div class="text-white/85">Hygiene, clothing, essentials</div>
                                    </div>

                                    <div class="rounded-2xl bg-white/12 backdrop-blur-md ring-1 ring-white/20 p-4">
                                        <div class="font-extrabold">Mentorship</div>
                                        <div class="text-white/85">Discipleship + coaching</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Donation widget (sticky on desktop) --}}
                <div id="give-form" class="lg:col-span-6 h-full scroll-mt-24 sm:scroll-mt-28 mt-4">
                    <div class="h-full lg:sticky lg:top-24">
                        <div class="rounded-3xl border border-slate-200 bg-white shadow-lg p-4 sm:p-6">
                            <div class="h-full rounded-3xl border border-slate-200 bg-white shadow-lg p-4 sm:p-6 flex flex-col">
                                <div>
                                    <h3 class="text-lg font-extrabold text-slate-900">Give now</h3>
                                    <p class="text-sm text-slate-600">Secure donation • Quick and simple</p>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-xs font-semibold">
                                    Encrypted
                                </span>
                            </div>

                            {{-- Widget --}}
                            <div class="pt-5 flex-1">
                                <div wire:ignore>
                                    <x-donation-widget />
                                </div>
                            </div>

                        </div>

                        {{-- Add later when more payments are available --}}
                        {{-- <p class="mt-3 text-xs text-slate-500">
                            Prefer giving another way? Add links here (PayPal, check address, etc.).
                        </p> --}}
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- VISIT / MAP --}}
    <section id="visit" class="scroll-mt-20 py-16 sm:py-20 bg-slate-50 border-y border-slate-200">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

                <div class="lg:col-span-5 space-y-5">
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight uppercase">
                        Visit us
                    </h2>

                    <div class="h-1 w-24 bg-slate-900 rounded-full"></div>

                    <div class="space-y-1 text-lg text-slate-700 font-medium">
                        <p>Every Thursday and Sunday at 11am</p>
                        <p>Township 9 Park,</p>
                        <p>Sacramento, CA</p>
                        <p>95811</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <a
                            href="https://goo.gl/maps/uD7kDihreYD3nXjcA"
                            class="inline-flex items-center justify-center rounded-full bg-slate-900 px-6 py-3
                                   text-sm font-semibold text-white hover:bg-slate-800 transition"
                        >
                            Get Directions
                        </a>

                        <a
                            href="#serve"
                            class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-6 py-3
                                   text-sm font-semibold text-slate-900 hover:bg-slate-100 transition"
                        >
                            What to expect
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="overflow-hidden rounded-3xl shadow-lg ring-1 ring-slate-200 bg-white">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3118.0671267815537!2d-121.49328698440546!3d38.60132517194608!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x809ad710c885a0bd%3A0x82f6d5b85e40b630!2sBread%20of%20Grace%20Ministries!5e0!3m2!1sen!2sus!4v1668623874529!5m2!1sen!2sus"
                            width="100%"
                            height="450"
                            style="border:0;"
                            allowfullscreen
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- FINAL CTA BAR --}}
    <x-final-cta give-href="#give-form" />

    {{-- FOOTER--}}
    <x-footer />

    {{-- Smooth scroll for anchor links --}}
    @once
        <script>
            document.addEventListener('click', function (e) {
                const a = e.target.closest('a[href^="#"]');
                if (!a) return;

                const id = a.getAttribute('href').slice(1);
                const el = document.getElementById(id);
                if (!el) return;

                e.preventDefault();

                const header = document.getElementById('site-header');
                const offset = (header?.offsetHeight ?? 0) + 12;

                const top = el.getBoundingClientRect().top + window.scrollY - offset;

                window.scrollTo({ top, behavior: 'smooth' });
                history.pushState(null, '', '#' + id);
            });
        </script>
    @endonce
</div>
