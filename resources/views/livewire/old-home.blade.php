<div class="bg-white text-slate-900">
    {{-- HERO --}}
    <section id="hero" class="relative overflow-hidden">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 pt-10 lg:pt-16 pb-12 lg:pb-20">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-10 items-center">
                {{-- Copy --}}
                <div class="xl:col-span-5 space-y-6">
                    <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight leading-tight">
                        Bring everyone together to build better community
                    </h1>

                    <p class="text-lg text-slate-600 leading-relaxed max-w-xl">
                        Bread of Grace Ministry is a Christ centered street ministry reaching and serving the homeless and at-risk community with the word of God, hot meal, survival supplies, employment and housing.
                    </p>

                    <div class="flex items-center gap-3">
                        <a href="#cta"
                           class="inline-flex items-center justify-center rounded-full bg-rose-700 px-6 py-3
                                  text-sm font-semibold text-white shadow-sm hover:bg-rose-800
                                  focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition">
                            Ready to Serve?
                        </a>

                        <a href="#about"
                           class="inline-flex items-center justify-center rounded-full border border-slate-300 px-6 py-3
                                  text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Learn more
                        </a>
                    </div>
                </div>

                {{-- Image --}}
                <div class="xl:col-span-7">
                    <div class="relative">
                        <div class="absolute -inset-6 bg-gradient-to-tr from-rose-200/40 via-white to-sky-200/40 blur-2xl"></div>
                        <img
                            src="{{ asset('images/the-mayor.jpeg') }}"
                            alt="Bread of Grace outreach"
                            class="relative w-full rounded-2xl object-cover shadow-xl ring-1 ring-slate-200"
                            loading="eager"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>


    {{-- ABOUT --}}
    <section id="about" class="py-14 sm:py-18">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">

                {{-- Left --}}
                <div class="lg:col-span-6 space-y-5">
                    <h2 class="text-3xl sm:text-4xl font-bold">
                        Bread of Grace Ministry at a glance
                    </h2>

                    <p class="text-lg text-slate-600 leading-relaxed">
                        Here at Bread of Grace Ministry, we know that sometimes all it takes to change the world is a little support.
                        Since our founding in 2010, we have been determined to make an impact. The core of our efforts is ministering
                        to the needs of the people through Bread of Grace Ministry's street outreach, Church Without Walls. Our ministry
                        provides food, clothing, and mentorship through our weekly church service, and weekly discipleship and Bible study.
                    </p>

                    <p class="text-lg text-slate-600 leading-relaxed">
                        Our current focus is to begin to implement our community development program whereby we create a living community
                        for our people. <span class="font-semibold text-slate-800">This will be done in 3 phases:</span>
                    </p>
                </div>

                {{-- Right / Phases --}}
                <div class="lg:col-span-6 space-y-6">
                    <div class="flex items-center gap-3">
                        <h3 class="text-2xl font-bold">
                            3 Phases to rehabilitation
                        </h3>
                    </div>

                    <ol class="space-y-4">
                        <li class="flex gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-700 text-white font-bold">
                                01
                            </div>
                            <div class="text-slate-700 text-base leading-relaxed font-medium">
                                Phase 1 is rehabilitation and community housing along with counseling and mentorship/discipleship.
                            </div>
                        </li>

                        <li class="flex gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-700 text-white font-bold">
                                02
                            </div>
                            <div class="text-slate-700 text-base leading-relaxed font-medium">
                                Phase 2 is education and job training along with counseling and mentorship/discipleship
                            </div>
                        </li>

                        <li class="flex gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-700 text-white font-bold">
                                03
                            </div>
                            <div class="text-slate-700 text-base leading-relaxed font-medium">
                                Phase 3 is permanent housing and career placement and continued mentorship/discipleship
                            </div>
                        </li>
                    </ol>
                </div>

            </div>
        </div>
    </section>


    {{-- PARALLAX BAND: Mentoring & Coaching --}}
    <section id="mentoring" class="bg-white">
        <div
            class="flex items-center justify-center flex-col py-24 md:py-32 bg-cover bg-center bg-no-repeat bg-fixed"
            style="
                background-image:
                    linear-gradient(0deg, rgba(0,0,0,0.70), rgba(0,0,0,0.35)),
                    url('{{ asset('images/bible-scriptures.jpeg') }}');
            "
        >
            <div class="w-full max-w-screen-2xl px-6 lg:px-12 2xl:px-20 text-center">
                <div class="border-t border-b border-white/70 py-10 md:py-12">
                    <h2 class="text-white text-3xl md:text-4xl font-bold tracking-wide uppercase">
                        Mentoring and Coaching
                    </h2>
                </div>

                <p class="mt-6 text-white/90 max-w-3xl mx-auto leading-relaxed">
                    We walk alongside people with consistent spiritual guidance,
                    practical life coaching, and Christ-centered community — helping
                    them rebuild identity, purpose, and momentum.
                </p>
            </div>
        </div>
    </section>


    {{-- COMMITMENT --}}
    <section id="commitment" class="py-14 sm:py-18">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

                <div class="lg:col-span-6">
                    <img
                        src="{{ asset('images/lisa-hug.jpeg') }}"
                        alt="Our commitment"
                        class="w-full rounded-2xl object-cover shadow-lg ring-1 ring-slate-200"
                        loading="lazy"
                    />
                </div>

                <div class="lg:col-span-6 space-y-5">
                    <h2 class="text-3xl sm:text-4xl font-bold">
                        Our Commitment
                    </h2>

                    <p class="text-lg text-slate-600 leading-relaxed max-w-xl">
                        Bread of Grace Ministry is a Christ centered street ministry reaching and serving the homeless and at-risk community with the word of God, hot meal, survival supplies, employment and housing.
                    </p>
                </div>
            </div>
        </div>
    </section>


    {{-- CONTACT / MAP --}}
    <section id="contact" class="py-14 sm:py-18 bg-slate-50 border-y border-slate-200">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

                {{-- Info --}}
                <div class="lg:col-span-5 space-y-5">
                    <h2 class="text-3xl sm:text-4xl font-bold uppercase">
                        Visit us at Bread of Grace Ministries
                    </h2>

                    <div class="h-1 w-24 bg-slate-900 rounded-full"></div>

                    <div class="space-y-1 text-lg text-slate-700 font-medium">
                        <p>Every Thursday and Sunday at 11am</p>
                        <p>Township 9 Park,</p>
                        <p>Sacramento, CA</p>
                        <p>95811</p>
                    </div>

                    <a
                        href="https://goo.gl/maps/uD7kDihreYD3nXjcA"
                        class="inline-flex items-center justify-center rounded-full border border-slate-900 bg-white px-6 py-3
                               text-sm font-semibold text-slate-900 hover:bg-slate-900 hover:text-white transition">
                        Get Directions
                    </a>
                </div>

                {{-- Map --}}
                <div class="lg:col-span-7">
                    <div class="overflow-hidden rounded-2xl shadow-lg ring-1 ring-slate-200 bg-white">
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


    {{-- CTA --}}
    <section id="cta" class="bg-rose-800">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 py-16 sm:py-20">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
                <div class="md:col-span-8 space-y-4">
                    <h2 class="text-white text-3xl sm:text-4xl font-extrabold leading-tight">
                        Start God's work and Join Us Today!
                    </h2>

                    <p class="text-white/90 text-lg leading-relaxed">
                        <span class="italic">
                            "And whoever gives one of these little ones only a cup of cold water in the name of a disciple,
                            assuredly, I say to you, he shall by no means lose his reward."
                        </span>
                        <br>
                        <span class="block mt-2 font-semibold">— Matthew 10:42</span>
                    </p>
                </div>

                <div class="md:col-span-4 flex md:justify-end">
                    <a href="#"
                       class="inline-flex items-center justify-center rounded-full bg-white px-7 py-3
                              text-sm font-semibold text-rose-800 shadow-sm hover:bg-slate-100 transition">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </section>


    {{-- FOOTER --}}
    <footer class="bg-slate-900 text-white">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 py-12">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

                {{-- Brand --}}
                <div class="lg:col-span-5 space-y-4">
                    <h3 class="text-2xl font-bold">Bread of Grace Ministries</h3>

                    <div class="flex items-center gap-4">
                        <a href="#" class="opacity-80 hover:opacity-100 transition">
                            <img src="{{ asset('images/icon-facebook.svg') }}" alt="Facebook" class="h-7 w-7">
                        </a>
                        <a href="#" class="opacity-80 hover:opacity-100 transition">
                            <img src="{{ asset('images/icon-youtube.svg') }}" alt="YouTube" class="h-7 w-7">
                        </a>
                        <a href="#" class="opacity-80 hover:opacity-100 transition">
                            <img src="{{ asset('images/icon-twitter.svg') }}" alt="Twitter" class="h-7 w-7">
                        </a>
                        <a href="#" class="opacity-80 hover:opacity-100 transition">
                            <img src="{{ asset('images/icon-pinterest.svg') }}" alt="Pinterest" class="h-7 w-7">
                        </a>
                        <a href="#" class="opacity-80 hover:opacity-100 transition">
                            <img src="{{ asset('images/icon-instagram.svg') }}" alt="Instagram" class="h-7 w-7">
                        </a>
                    </div>
                </div>

                {{-- Nav --}}
                <div class="lg:col-span-3">
                    <div class="flex flex-col gap-3 text-lg font-semibold">
                        <a href="#about" class="hover:text-rose-300 transition">About</a>
                        <a href="#hero" class="hover:text-rose-300 transition">Top</a>
                    </div>
                </div>

                {{-- Newsletter --}}
                <div class="lg:col-span-4">
                    <form action="#" class="space-y-3">
                        <div class="flex gap-2">
                            <input
                                type="text"
                                class="w-full rounded-full px-4 py-2 text-slate-900 placeholder:text-slate-400
                                       focus:outline-none focus:ring-2 focus:ring-rose-400"
                                placeholder="Updated in your inbox"
                            />
                            <button
                                class="shrink-0 rounded-full bg-rose-600 px-6 py-2 font-semibold
                                       hover:bg-rose-500 transition">
                                Go
                            </button>
                        </div>

                        <p class="text-sm text-white/70">
                            Copyright &copy; {{ now()->year }}, All Rights Reserved
                        </p>
                    </form>
                </div>

            </div>
        </div>
    </footer>

    {{-- Optional JS fallback if you *don’t* add html { scroll-behavior:smooth } --}}
    @once
        <script>
            document.addEventListener('click', function (e) {
                const a = e.target.closest('a[href^="#"]');
                if (!a) return;

                const id = a.getAttribute('href').slice(1);
                const el = document.getElementById(id);
                if (!el) return;

                e.preventDefault();
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.pushState(null, '', '#' + id);
            });
        </script>
    @endonce
</div>
