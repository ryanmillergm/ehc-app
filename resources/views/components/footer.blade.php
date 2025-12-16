@props([
    'year' => now()->year,
])

@php
    $giveUrl = request()->routeIs('donations.show') || request()->routeIs('home') ? '#give-form' : url('/give#give-form');
@endphp

<footer {{ $attributes->class(['relative overflow-hidden bg-slate-950 text-white']) }}>
    {{-- subtle glow --}}
    <div aria-hidden="true" class="absolute inset-0">
        <div class="absolute -top-32 -right-24 h-80 w-80 rounded-full bg-rose-500/10 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-indigo-500/10 blur-3xl"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950 via-slate-950 to-black"></div>
    </div>

    <div class="relative mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 pt-14 pb-10">
        <div class="mt-10 grid grid-cols-1 lg:grid-cols-12 gap-10">

            {{-- brand --}}
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

                {{-- socials --}}
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

            {{-- links --}}
            <div class="lg:col-span-3 text-center sm:text-left">
                <div class="text-lg font-semibold text-white/80">Explore</div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm justify-items-center sm:justify-items-start">
                    <a href="{{ url('/#about') }}" class="rounded-xl px-3 py-2 hover:bg-white/5 text-white/80 hover:text-white transition">About</a>
                    <a href="{{ url('/#serve') }}" class="rounded-xl px-3 py-2 hover:bg-white/5 text-white/80 hover:text-white transition">Serve</a>
                    <a href="{{ $giveUrl }}" class="rounded-xl px-3 py-2 hover:bg-white/5 text-white/80 hover:text-white transition">Give</a>
                    <a href="{{ url('/#visit') }}" class="rounded-xl px-3 py-2 hover:bg-white/5 text-white/80 hover:text-white transition">Visit</a>
                </div>
            </div>

            {{-- newsletter --}}
            <div class="lg:col-span-4">
                <div class="rounded-3xl bg-white/5 ring-1 ring-white/10 p-6">
                    <div class="text-sm font-semibold">Stay connected</div>
                    <p class="mt-1 text-sm text-white/65">
                        Monthly updates: outreach stories, needs, and ways to help.
                    </p>

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

        {{-- bottom bar --}}
        <div class="mt-12 pt-8 border-t border-white/10 flex flex-col items-center gap-3 text-xs text-white/55 text-center sm:flex-row sm:justify-between sm:text-left">
            <div>
                © {{ $year }} Bread of Grace Ministries. All rights reserved.
            </div>

            <div class="flex items-center justify-center gap-4">
                <a href="{{ $giveUrl }}" class="hover:text-white transition">Donate</a>
                <a href="{{ url('/#visit') }}" class="hover:text-white transition">Directions</a>
                <a href="{{ url('/#about') }}" class="hover:text-white transition">Mission</a>
            </div>
        </div>
    </div>
</footer>
