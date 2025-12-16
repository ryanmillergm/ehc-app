{{-- Embeds resources\views\components\donation-widget.blade.php --}}
<x-layouts.app title="Give">
    <main class="bg-white text-slate-900">
        {{-- Simple hero header --}}
        <section class="relative overflow-hidden">
            {{-- Background --}}
            <div class="absolute inset-0">
                <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>
                <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-rose-200/40 blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl"></div>
            </div>

            <div class="relative mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 pt-24 pb-12">
                <div class="max-w-3xl mx-auto text-center">
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 text-white px-4 py-1.5 text-xs font-semibold tracking-wide">
                            Bread of Grace Ministries
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                            Sacramento, CA
                        </span>

                        <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 px-4 py-1.5 text-xs font-semibold">
                            Give • One-time or Monthly
                        </span>

                        <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-4 py-1.5 text-xs font-semibold">
                            Secure giving
                        </span>
                    </div>

                    <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold tracking-tight leading-[1.05]">
                        Make outreach possible
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-500 to-rose-700">
                            this week.
                        </span>
                    </h1>

                    <p class="mt-4 text-lg text-slate-600 leading-relaxed">
                        Your gift supports hot meals, survival supplies, discipleship, and practical next steps toward stability.
                        Monthly giving helps us plan with confidence.
                    </p>

            <div class="mx-auto max-w-screen-4xl py-4">
                <div class="max-w-3xl mx-auto">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-lg p-4 sm:p-6">
                        {{-- Header --}}
                        <div class="flex justify-center lg:items-start lg:justify-between lg:gap-4 pb-4 border-b border-slate-200">
                            <div>
                                <h2 class="text-lg font-extrabold text-slate-900">Give now</h2>
                                <p class="text-sm text-slate-600">Quick and simple • Choose amount + frequency</p>
                            </div>

                            <span class="hidden lg:inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-xs font-semibold">
                                Encrypted
                            </span>
                        </div>

                        {{-- Widget --}}
                        <div class="pt-5">
                            <div wire:ignore>
                                <x-donation-widget />
                            </div>
                        </div>

                        {{-- helper links --}}
                        <div class="pt-6 mt-6 border-t border-slate-200 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between text-sm">
                            <a href="{{ url('/#serve') }}" class="text-slate-600 hover:text-slate-900 transition">
                                Want to serve too? Volunteer →
                            </a>

                            <a href="{{ url('/#visit') }}" class="text-slate-600 hover:text-slate-900 transition">
                                Visiting? Get directions →
                            </a>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-slate-500 text-center">
                        Thank you for supporting consistent outreach and long-term restoration.
                    </p>
                </div>
            </div>

                    {{-- quick impact chips --}}
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-2 text-sm">
                        <span class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-slate-200 text-slate-700">
                            Meals served with dignity
                        </span>
                        <span class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-slate-200 text-slate-700">
                            Hygiene + essentials
                        </span>
                        <span class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-slate-200 text-slate-700">
                            Mentorship + discipleship
                        </span>
                    </div>
                </div>
            </div>
        </section>

        {{-- FINAL CTA BAR (matches home landing page vibe) --}}
        <section class="bg-gradient-to-r from-rose-800 to-slate-900">
            <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 py-14 sm:py-16">
                <div class="rounded-3xl border border-white/20 bg-slate-950/90 backdrop-blur-md p-6 sm:p-8">
                    <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/85 ring-1 ring-white/10">
                                Church Without Walls • Sacramento
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                                Thu + Sun 11am
                            </div>
                            <h3 class="mt-3 text-white text-2xl sm:text-3xl font-extrabold tracking-tight">
                                Be part of someone’s next step.
                            </h3>
                            <p class="mt-2 max-w-2xl text-white/70">
                                Give today, serve this week, or visit in person — your presence and generosity help change real lives.
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="#give-form"
                               class="inline-flex items-center justify-center rounded-full bg-rose-600 px-7 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 transition">
                                Give now
                            </a>
                            <a href="{{ url('/#serve') }}"
                               class="inline-flex items-center justify-center rounded-full bg-white/10 px-7 py-3.5 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-white/15 transition">
                                Volunteer
                            </a>
                            <a href="{{ url('/#visit') }}"
                               class="inline-flex items-center justify-center rounded-full px-7 py-3.5 text-sm font-semibold text-white/80 hover:text-white transition">
                                Visit →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FOOTER (same as home) --}}
        <footer class="relative overflow-hidden bg-slate-950 text-white">
            <div aria-hidden="true" class="absolute inset-0">
                <div class="absolute -top-32 -right-24 h-80 w-80 rounded-full bg-rose-500/10 blur-3xl"></div>
                <div class="absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-indigo-500/10 blur-3xl"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950 via-slate-950 to-black"></div>
            </div>

            <div class="relative mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 pt-14 pb-10">
                <div class="mt-10 grid grid-cols-1 lg:grid-cols-12 gap-10">
                    <div class="lg:col-span-5 space-y-4">
                        <div class="flex items-center justify-center sm:justify-start gap-3">
                            <div class="h-11 w-11 rounded-2xl bg-white/10 ring-1 ring-white/10 flex items-center justify-center font-extrabold">
                                BG
                            </div>
                            <div class="text-center sm:text-left">
                                <div class="text-lg font-extrabold">Bread of Grace Ministries</div>
                                <div class="text-sm text-white/60">Christ-centered street outreach</div>
                            </div>
                        </div>

                        <p class="text-white/70 max-w-xl leading-relaxed text-center sm:text-left">
                            Serving the homeless and at-risk community with the Word of God and practical support:
                            meals, supplies, mentorship, and pathways to housing.
                        </p>

                        <div class="flex items-center justify-center sm:justify-start gap-3 pt-1">
                            <a href="#" class="group inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white/5 ring-1 ring-white/10 hover:bg-white/10 transition" aria-label="Facebook">
                                <img src="{{ asset('images/icon-facebook.svg') }}" alt="" class="h-6 w-6 opacity-85 group-hover:opacity-100 transition">
                            </a>
                            <a href="#" class="group inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white/5 ring-1 ring-white/10 hover:bg-white/10 transition" aria-label="YouTube">
                                <img src="{{ asset('images/icon-youtube.svg') }}" alt="" class="h-6 w-6 opacity-85 group-hover:opacity-100 transition">
                            </a>
                            <a href="#" class="group inline-flex items-center justify-center h-11 w-11 rounded-2xl bg-white/5 ring-1 ring-white/10 hover:bg-white/10 transition" aria-label="Instagram">
                                <img src="{{ asset('images/icon-instagram.svg') }}" alt="" class="h-6 w-6 opacity-85 group-hover:opacity-100 transition">
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-3 text-center sm:text-left">
                        <div class="text-lg font-semibold text-white/80">Explore</div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm justify-items-center sm:justify-items-start">
                            <a href="{{ url('/#about') }}" class="rounded-xl px-3 py-2 bg-white/0 hover:bg-white/5 text-white/80 hover:text-white transition">About</a>
                            <a href="{{ url('/#serve') }}" class="rounded-xl px-3 py-2 bg-white/0 hover:bg-white/5 text-white/80 hover:text-white transition">Serve</a>
                            <a href="#give-form" class="rounded-xl px-3 py-2 bg-white/0 hover:bg-white/5 text-white/80 hover:text-white transition">Give</a>
                            <a href="{{ url('/#visit') }}" class="rounded-xl px-3 py-2 bg-white/0 hover:bg-white/5 text-white/80 hover:text-white transition">Visit</a>
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <div class="rounded-3xl bg-white/5 ring-1 ring-white/10 p-6">
                            <div>
                                <div class="text-sm font-semibold">Stay connected</div>
                                <p class="mt-1 text-sm text-white/65">
                                    Monthly updates: outreach stories, needs, and ways to help.
                                </p>
                            </div>

                            <form action="#" class="mt-5 space-y-3">
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <input
                                        type="email"
                                        class="w-full rounded-full px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-400"
                                        placeholder="Email address"
                                    />
                                    <button class="shrink-0 rounded-full bg-rose-600 px-6 py-3 font-semibold hover:bg-rose-500 transition">
                                        Subscribe
                                    </button>
                                </div>

                                <div class="text-xs text-white/55 text-center sm:text-left">
                                    By subscribing, you agree to receive email updates.
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="mt-12 pt-8 border-t border-white/10 flex flex-col items-center gap-3 text-xs text-white/55 text-center sm:flex-row sm:justify-between sm:text-left">
                    <div>
                        © {{ now()->year }} Bread of Grace Ministries. All rights reserved.
                    </div>

                    <div class="flex items-center justify-center gap-4">
                        <a href="#give-form" class="hover:text-white transition">Donate</a>
                        <a href="{{ url('/#visit') }}" class="hover:text-white transition">Directions</a>
                        <a href="{{ url('/#about') }}" class="hover:text-white transition">Mission</a>
                    </div>
                </div>
            </div>
        </footer>

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
    </main>
</x-layouts.app>
