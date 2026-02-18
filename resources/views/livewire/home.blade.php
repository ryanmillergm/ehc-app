<div class="bg-white text-slate-900">
    @php
        $heroSection = $sections['hero'] ?? [];
        $impactStats = $sections['impact_stats']['items'] ?? [];
        $aboutSection = $sections['about'] ?? [];
        $pathwaySection = $sections['pathway'] ?? [];
        $parallaxSection = $sections['parallax'] ?? [];
        $serveSection = $sections['serve'] ?? [];
        $serveSupportSection = $sections['serve_support'] ?? [];
        $preGiveCta = $sections['pre_give_cta'] ?? [];
        $giveSection = $sections['give'] ?? [];
        $visitSection = $sections['visit'] ?? [];
        $finalCta = $sections['final_cta'] ?? [];
    @endphp

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
                            {{ $heroSection['eyebrow'] ?? 'Bread of Grace Ministries' }}
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                            {{ $heroSection['location'] ?? 'Sacramento, CA' }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 px-4 py-1.5 text-xs font-semibold">
                            {{ $heroSection['subheading'] ?? 'Serving since 2010' }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-4 py-1.5 text-xs font-semibold">
                            {{ $heroSection['note'] ?? 'Church Without Walls • Thu + Sun 11am' }}
                        </span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.05]">
                        {{ $heroSection['heading'] ?? "Help restore lives through God's Word and practical support." }}
                    </h1>

                    <p class="text-lg sm:text-xl text-slate-600 leading-relaxed max-w-2xl">
                        {{ $heroIntro }}
                    </p>

                    {{-- Image --}}
                    <div class="md:hidden">
                        <div class="relative">
                            <div class="absolute -inset-6 bg-gradient-to-tr from-rose-200/40 via-white to-sky-200/40 blur-2xl"></div>
                            <img
                                src="{{ $homeImages['hero_primary'] ?? asset('images/sm/the-mayor.jpg') }}"
                                alt="Bread of Grace outreach"
                                class="relative w-full rounded-2xl object-cover shadow-xl ring-1 ring-slate-200"
                                loading="eager"
                            />
                        </div>
                    </div>

                    {{-- Primary CTAs --}}
                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                        <a href="{{ $heroSection['ctas'][0]['url'] ?? '#give-form' }}"
                           class="inline-flex items-center justify-center rounded-full bg-rose-700 px-7 py-3.5
                                  text-lg font-semibold text-white shadow-sm hover:bg-rose-800
                                  focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition">
                            {{ $heroSection['ctas'][0]['label'] ?? 'Give today' }}
                            <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10.75 5.75a.75.75 0 0 0-1.5 0V9.25H5.75a.75.75 0 0 0 0 1.5H9.25v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5V5.75Z" />
                            </svg>
                        </a>

                        <a href="{{ $heroSection['ctas'][1]['url'] ?? '#serve' }}"
                           class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-7 py-3.5
                                  text-lg font-semibold text-slate-800 hover:bg-slate-50 transition">
                            {{ $heroSection['ctas'][1]['label'] ?? 'Volunteer with us' }}
                        </a>

                        <a href="{{ $heroSection['ctas'][2]['url'] ?? '#visit' }}"
                           class="inline-flex items-center justify-center rounded-full text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
                            {{ $heroSection['ctas'][2]['label'] ?? 'Visit Thursday/Sunday →' }}
                        </a>
                    </div>

                    {{-- “Quick choices” cards --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                        @foreach (($heroSection['quick_choices'] ?? []) as $choice)
                            <a href="{{ $choice['url'] ?? '#' }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition">
                                <p class="text-md font-semibold text-rose-700">{{ $choice['label'] ?? '' }}</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $choice['title'] ?? '' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $choice['description'] ?? '' }}</p>
                            </a>
                        @endforeach
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
                                    src="{{ $homeImages['hero_primary'] ?? asset('images/sm/the-mayor.jpg') }}"
                                    alt="Bread of Grace outreach"
                                    class="w-full aspect-[16/11] md:aspect-auto md:h-[420px] rounded-3xl object-cover shadow-xl ring-1 ring-slate-200"
                                    loading="lazy"
                                />
                            </div>

                            {{-- Right stack --}}
                            <div class="md:col-span-5 space-y-4">
                                <img
                                    src="{{ $homeImages['hero_secondary'] ?? asset('images/sm/lisa-hug.jpg') }}"
                                    alt="Love and support"
                                    class="w-full aspect-[16/10] md:aspect-auto md:h-[200px] rounded-3xl object-cover shadow-lg ring-1 ring-slate-200"
                                    loading="lazy"
                                />

                                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-xs font-semibold text-slate-600">Scripture that shapes our work</p>
                                    <p class="mt-2 text-sm text-slate-800 leading-relaxed italic">
                                        {{ $heroSection['scripture_text'] ?? '“And whoever gives one of these little ones only a cup of cold water... shall by no means lose his reward.”' }}
                                    </p>
                                    <p class="mt-2 text-xs font-semibold text-slate-900">— {{ $heroSection['scripture_reference'] ?? 'Matthew 10:42' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Floating “next meetup” pill --}}
                        <div class="pointer-events-none absolute -bottom-8 sm:-bottom-5 lg:-bottom-20 left-6 right-6">
                            <div class="mx-auto max-w-xl rounded-full bg-slate-900/90 text-white px-5 py-3 shadow-lg ring-1 ring-white/10 backdrop-blur">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs sm:text-sm">
                                    <span class="font-semibold">Meet us: {{ $heroSection['meeting_schedule'] ?? $meetingSchedule }}</span>
                                    <span class="text-white/80">{{ $heroSection['meeting_location'] ?? $meetingLocation }}</span>
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
                @foreach ($impactStats as $stat)
                    <div class="rounded-2xl bg-white/5 border border-slate-200/20 ring-1 ring-white/10 p-6">
                        <div class="text-3xl lg:text-4xl font-extrabold text-white">{{ $stat['title'] ?? '' }}</div>
                        <div class="mt-2 text-white/75">{{ $stat['description'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>



    {{-- ABOUT / HOW IT WORKS --}}
    <section id="about" class="scroll-mt-20 py-16 sm:py-20">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

                <div class="lg:col-span-5 space-y-5">
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                        {{ $aboutSection['heading'] ?? 'A simple path to restoration.' }}
                    </h2>
                    <p class="text-lg text-slate-600 leading-relaxed">
                        {{ $aboutSection['body'] ?? 'We believe transformation is spiritual and practical. So we combine consistent discipleship with tangible steps that rebuild stability and dignity.' }}
                    </p>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-sm font-semibold text-slate-900">{{ $aboutSection['note'] ?? "What you'll see in our outreach" }}</p>
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-slate-700">
                            @foreach (($aboutSection['bullets'] ?? []) as $bullet)
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-rose-600"></span>
                                    {{ $bullet['title'] ?? '' }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-6 sm:px-8 py-6 border-b border-slate-200 bg-gradient-to-r from-rose-50 to-indigo-50">
                            <h3 class="text-xl sm:text-3xl sm:font-extrabold font-extrabold">{{ $pathwaySection['heading'] ?? '3 phases to rehabilitation' }}</h3>
                            <p class="mt-1 text-slate-600 font-medium">{{ $pathwaySection['subheading'] ?? 'Built for real life: spiritual foundation + next practical step.' }}</p>
                        </div>

                        <ol class="p-6 sm:p-8 space-y-4">
                            @foreach (($pathwaySection['items'] ?? []) as $phase)
                                <li class="group flex gap-4 rounded-2xl border border-slate-200 p-5 hover:shadow-md transition">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-900 text-white font-extrabold">
                                        {{ $phase['label'] ?? '' }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ $phase['title'] ?? '' }}</div>
                                        <div class="mt-1 text-slate-600">{{ $phase['description'] ?? '' }}</div>
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
                    url('{{ $parallaxSection['background_image'] ?? asset('images/sm/bible-scriptures.jpg') }}');
            "
        >
            <div class="w-full max-w-screen-2xl px-6 lg:px-12 2xl:px-20 text-center">
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-5 py-2 text-white/90 text-xs font-semibold tracking-widest uppercase ring-1 ring-white/15">
                    {{ $parallaxSection['eyebrow'] ?? 'Mentoring • Coaching • Discipleship' }}
                </div>

                <h2 class="mt-5 text-white text-3xl md:text-5xl font-extrabold tracking-tight">
                    {{ $parallaxSection['heading'] ?? 'Nobody rebuilds alone.' }}
                </h2>

                <p class="mt-4 text-white/90 max-w-3xl mx-auto leading-relaxed text-lg">
                    {{ $parallaxSection['body'] ?? 'We walk alongside people with consistent spiritual guidance, practical life coaching, and Christ-centered community - helping restore identity, purpose, and momentum.' }}
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
                            style="background-image: url('{{ $serveSection['background_image'] ?? asset('images/sm/bike-path-road.jpg') }}');"
                        ></div>

                        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-950/60 to-slate-950/30"></div>
                        <div class="absolute inset-0 bg-black/10"></div>

                        {{-- Content (vertically centered) --}}
                        <div class="relative flex h-full min-h-[400px] items-center p-7 sm:p-10 lg:p-12">
                            <div class="w-full">
                                <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-xs font-semibold tracking-wide text-white ring-1 ring-white/20">
                                    {{ $serveSection['eyebrow'] ?? 'Serve • Outreach Team' }}
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                                    {{ $serveSection['location'] ?? 'Sacramento' }}
                                </div>

                                <h2 class="mt-4 text-3xl sm:text-4xl font-extrabold tracking-tight text-white drop-shadow">
                                    {{ $serveSection['heading'] ?? 'Serve with Bread of Grace.' }}
                                </h2>

                                <p class="mt-3 text-lg leading-relaxed text-white/95 max-w-2xl drop-shadow">
                                    {{ $serveSection['body'] ?? "Some people give. Some people show up. Some do both. There's a place for you - prayer, food service, conversations, discipleship, logistics." }}
                                </p>

                                <div class="mt-6 flex flex-col sm:flex-row gap-3">
                                    <a href="{{ $serveSection['ctas'][0]['url'] ?? route('volunteer.apply', ['need' => 'general']) }}"
                                       class="inline-flex items-center justify-center rounded-full bg-white/90 px-7 py-3.5
                                              text-sm font-semibold text-black ring-1 ring-white/60 hover:bg-white/65 transition">
                                        {{ $serveSection['ctas'][0]['label'] ?? 'Sign up to Volunteer' }}
                                    </a>

                                    <a href="{{ $serveSection['ctas'][1]['url'] ?? '#give-form' }}"
                                       class="inline-flex items-center justify-center rounded-full bg-rose-600 px-7 py-3.5
                                              text-sm font-semibold text-white shadow-sm hover:bg-rose-700 transition">
                                        {{ $serveSection['ctas'][1]['label'] ?? 'Support the work' }}
                                    </a>

                                    <a href="{{ $serveSection['ctas'][2]['url'] ?? '#visit' }}"
                                       class="inline-flex items-center justify-center rounded-full bg-white/10 px-7 py-3.5
                                              text-sm font-semibold text-white ring-1 ring-white/20 hover:bg-white/15 transition">
                                        {{ $serveSection['ctas'][2]['label'] ?? 'Come this Thursday/Sunday' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
                        <div class="text-sm font-semibold text-slate-900">{{ $serveSupportSection['heading'] ?? 'A few “easy yes” ways to help' }}</div>
                        <ul class="mt-4 space-y-3 text-slate-700">
                            @foreach (($serveSupportSection['items'] ?? []) as $easyYes)
                                <li class="flex gap-3">
                                    <span class="mt-1 h-2.5 w-2.5 rounded-full bg-rose-600"></span>
                                    {{ $easyYes['title'] ?? '' }}
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-6 rounded-2xl bg-rose-50 border border-rose-100 p-4">
                            <p class="text-sm text-rose-900">
                                <span class="font-semibold">Pro tip:</span> {{ $serveSupportSection['tip'] ?? 'People remember warmth and consistency more than speeches. Just showing up matters.' }}
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
                            {{ $preGiveCta['eyebrow'] ?? 'Next step' }}
                        </div>
                        <div class="mt-2 text-2xl sm:text-3xl font-extrabold text-slate-900">
                            {{ $preGiveCta['heading'] ?? 'Ready to make a real difference today?' }}
                        </div>
                        <p class="mt-2 text-slate-600 max-w-2xl">
                            {{ $preGiveCta['body'] ?? 'Your gift helps meals, supplies, and consistent discipleship happen every week.' }}
                        </p>
                    </div>

                    <a href="{{ $preGiveCta['url'] ?? '#give-form' }}"
                       class="shrink-0 inline-flex items-center justify-center rounded-full bg-rose-700 px-7 py-3.5
                              text-sm font-semibold text-white shadow-sm hover:bg-rose-800 transition">
                        {{ $preGiveCta['label'] ?? 'Jump to donation form →' }}
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
                        {{ $giveSection['eyebrow'] ?? 'Give • One-time or Monthly' }}
                    </div>

                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                        {{ $giveSection['heading'] ?? 'Make outreach possible this week.' }}
                    </h2>

                    <p class="text-lg text-slate-600 leading-relaxed max-w-2xl">
                        {{ $giveSection['body'] ?? 'Your gift helps feed the hungry and help those in need through meals, survival supplies, discipleship, and practical next steps toward stability. Monthly giving helps us plan with confidence.' }}
                    </p>

                    <div class="relative h-[600px] sm:h-[400px] overflow-hidden rounded-3xl text-white p-6 sm:p-8">
                        {{-- Background image --}}
                        <div
                            class="absolute inset-0 bg-cover bg-center"
                            style="background-image: url('{{ $giveSection['background_image'] ?? asset('images/sm/group-joseph-peace.jpg') }}');"
                        ></div>

                        {{-- Overlay for readability --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/70 via-slate-950/45 to-slate-950/20"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-black/0 to-black/0"></div>

                        {{-- Content --}}
                        <div class="relative flex h-full flex-col">
                            <div class="mt-auto">
                                <p class="text-sm font-semibold text-white/85 drop-shadow">{{ $giveSection['heart_label'] ?? 'Our heart' }}</p>

                                <p class="mt-3 text-lg leading-relaxed italic text-white/95 drop-shadow">
                                    {{ $giveSection['scripture'] ?? '“For I was hungry and you gave Me food; I was thirsty and you gave Me drink; I was a stranger and you took Me in...”' }}
                                </p>

                                <p class="mt-3 text-sm font-semibold text-white/90 drop-shadow">— {{ $giveSection['scripture_reference'] ?? 'Matthew 25:35' }}</p>

                                <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                                    @foreach (($giveSection['impact_cards'] ?? []) as $card)
                                        <div class="rounded-2xl bg-white/12 backdrop-blur-md ring-1 ring-white/20 p-4">
                                            <div class="font-extrabold">{{ $card['title'] ?? '' }}</div>
                                            <div class="text-white/85">{{ $card['description'] ?? '' }}</div>
                                        </div>
                                    @endforeach
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
                                    <h3 class="text-lg font-extrabold text-slate-900">{{ $giveSection['give_now_label'] ?? 'Give now' }}</h3>
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
                        {{ $visitSection['heading'] ?? 'Visit us' }}
                    </h2>

                    <div class="h-1 w-24 bg-slate-900 rounded-full"></div>

                    <div class="space-y-1 text-lg text-slate-700 font-medium">
                        <p>{{ $visitSection['meeting_schedule'] ?? $meetingSchedule }}</p>
                        <p>{{ $visitSection['meeting_location'] ?? $meetingLocation }}</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <a
                            href="{{ $visitSection['directions_url'] ?? 'https://goo.gl/maps/uD7kDihreYD3nXjcA' }}"
                            class="inline-flex items-center justify-center rounded-full bg-slate-900 px-6 py-3
                                   text-sm font-semibold text-white hover:bg-slate-800 transition"
                        >
                            {{ $visitSection['directions_label'] ?? 'Get Directions' }}
                        </a>

                        {{-- Need to add an about page with what to expect when you visit --}}
                        {{-- <a
                            href="{{ route('volunteer.apply', ['need' => 'general']) }}"
                            class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-6 py-3
                                   text-sm font-semibold text-slate-900 hover:bg-slate-100 transition"
                        >
                            What to expect
                        </a> --}}
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="overflow-hidden rounded-3xl shadow-lg ring-1 ring-slate-200 bg-white">
                        <iframe
                            src="{{ $visitSection['map_embed_url'] ?? 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3118.0671267815537!2d-121.49328698440546!3d38.60132517194608!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x809ad710c885a0bd%3A0x82f6d5b85e40b630!2sBread%20of%20Grace%20Ministries!5e0!3m2!1sen!2sus!4v1668623874529!5m2!1sen!2sus' }}"
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

    @include('partials.faq-grid', [
        'faqItems' => $faqItems,
        'heading' => 'Frequently asked questions',
        'subheading' => 'Common questions about helping the homeless in Sacramento through Bread of Grace Ministries.',
    ])

    {{-- FINAL CTA BAR --}}
    <x-final-cta
        :eyebrow="$finalCta['eyebrow'] ?? 'Church Without Walls • Sacramento • Thu + Sun 11am'"
        :heading="$finalCta['heading'] ?? 'Be part of someone’s next step.'"
        :body="$finalCta['body'] ?? 'Give today, serve this week, or visit in person — your presence and generosity help change real lives.'"
        :giveLabel="$finalCta['label'] ?? 'Give now'"
        :giveHref="$finalCta['url'] ?? '#give-form'"
        volunteerHref="#serve"
        visitHref="#visit"
    />

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
