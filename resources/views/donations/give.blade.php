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

            <div id="give-form" class="mx-auto max-w-screen-4xl py-4">
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
    </main>
</x-layouts.app>
