{{-- resources/views/partials/navbar.blade.php --}}

@php
    use App\Models\Language;

    // Make partial safe everywhere (guest + app layouts)
    $languages = $languages
        ?? Language::query()->orderBy('title')->get();

    $currentLanguage = $currentLanguage
        ?? Language::find(session('language_id'))
        ?? Language::first();
@endphp

<nav
    id="main-navbar"
    class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-200
           transform transition-transform duration-300"
>
    <div class="mx-auto w-full max-w-7xl xl:max-w-screen-2xl px-4 sm:px-6 lg:px-10">
        <div class="flex h-16 items-center justify-between gap-4">
            {{-- Left: Logo --}}
            <div class="flex items-center">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <img
                        src="{{ asset('images/logos/bread-of-grace-logo-clean.png') }}"
                        alt="Bread of Grace Ministries"
                        class="h-8 w-auto sm:h-9"
                    />
                </a>
            </div>

            {{-- Center: Nav links (desktop) --}}
            <div class="hidden md:flex flex-1 justify-center">
                <div class="flex items-center gap-6 text-sm font-medium text-slate-700">
                    <a href="{{ url('/') }}" class="hover:text-indigo-600 transition-colors">Home</a>
                    <a href="{{ url('/pages') }}" class="hover:text-indigo-600 transition-colors">Pages</a>
                    <a href="{{ url('/children') }}" class="hover:text-indigo-600 transition-colors">Children</a>
                    <a href="{{ url('/teams') }}" class="hover:text-indigo-600 transition-colors">Teams</a>
                </div>
            </div>

            {{-- Right side (desktop): language picker + auth --}}
            <div class="hidden md:flex items-center justify-end gap-4 text-sm">
                {{-- Language picker --}}
                <div class="relative">
                    <label for="lang-select" class="sr-only">Language</label>
                    <select
                        id="lang-select"
                        data-lang-select
                        class="appearance-none rounded-full border border-slate-200 bg-white
                               px-3 py-1.5 pr-12 text-xs font-medium text-slate-700
                               hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        @foreach ($languages as $lang)
                            <option
                                value="{{ $lang->locale }}"
                                @selected($currentLanguage?->id === $lang->id)
                            >
                                {{ $lang->title }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Custom chevron --}}
                    <svg
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-500"
                        viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </div>

                {{-- Auth / user --}}
                @auth
                    <span class="hidden lg:inline-block text-slate-700">
                        Hi, {{ Auth::user()->fullName }}
                    </span>

                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center rounded-full border border-slate-200
                              px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        Dashboard
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-full bg-indigo-600 px-3 py-1.5
                                   text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                        >
                            Log out
                        </button>
                    </form>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center rounded-full border border-slate-200
                                  px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Log in
                        </a>
                    @endif

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center rounded-full bg-indigo-600 px-3 py-1.5
                                  text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
                            Sign up
                        </a>
                    @endif
                @endauth
            </div>

            {{-- Mobile: hamburger --}}
            <div class="flex items-center md:hidden">
                <button
                    id="mobile-menu-toggle"
                    type="button"
                    class="md:hidden inline-flex items-center justify-center p-2 rounded-full
                           bg-white/80 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    aria-label="Toggle navigation"
                    data-state="closed"
                >
                    <div class="navbar-hamburger-icon transition-transform duration-300">
                        <span class="navbar-hamburger-line navbar-hamburger-line-top"></span>
                        <span class="navbar-hamburger-line navbar-hamburger-line-middle"></span>
                        <span class="navbar-hamburger-line navbar-hamburger-line-bottom"></span>
                    </div>
                </button>
            </div>
        </div>
    </div>
</nav>

{{-- Mobile overlay + panel --}}
<div
    id="mobile-menu-overlay"
    class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm opacity-0 pointer-events-none
           transition-opacity duration-300 md:hidden"
>
    <div
        id="mobile-menu-panel"
        class="absolute inset-y-0 right-0 w-full sm:w-[380px] bg-slate-900 text-white
               transform translate-x-full transition-transform duration-300 flex flex-col"
    >
        {{-- Panel header with close button --}}
        <div class="flex items-center justify-between px-6 h-16 border-b border-slate-800">
            <div class="flex flex-col">
                <span class="text-sm font-semibold">{{ config('app.name', 'Laravel') }}</span>
                <span class="text-xs text-slate-400">Menu</span>
            </div>

            <button
                id="mobile-menu-close"
                type="button"
                class="inline-flex items-center justify-center p-2 rounded-full text-slate-300 hover:text-white
                       focus:outline-none focus:ring-2 focus:ring-indigo-500"
                aria-label="Close navigation"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M4 4l12 12m0-12L4 16"
                          stroke="currentColor"
                          stroke-width="1.8"
                          stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-6">
            {{-- Mobile language picker --}}
            <div>
                <label for="lang-select-mobile" class="block text-xs text-slate-300 mb-2">
                    Language
                </label>

                <div class="relative">
                    <select
                        id="lang-select-mobile"
                        data-lang-select
                        class="w-full appearance-none rounded-lg border border-slate-700 bg-slate-800
                               px-3 py-2 pr-10 text-sm text-white
                               focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        @foreach ($languages as $lang)
                            <option
                                value="{{ $lang->locale }}"
                                @selected($currentLanguage?->id === $lang->id)
                            >
                                {{ $lang->title }}
                            </option>
                        @endforeach
                    </select>

                    <svg
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-300"
                        viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>

            {{-- Mobile nav links --}}
            <nav class="space-y-1 text-sm">
                <a href="{{ url('/') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Home</a>
                <a href="{{ url('/pages') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Pages</a>
                <a href="{{ url('/children') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Children</a>
                <a href="{{ url('/teams') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Teams</a>
            </nav>

            <div class="pt-4 border-t border-slate-800/60">
                @auth
                    <p class="text-xs text-slate-400 mb-3">
                        Signed in as <span class="font-medium text-slate-100">{{ Auth::user()->name }}</span>
                    </p>

                    <a
                        href="{{ route('dashboard') }}"
                        class="block w-full text-center rounded-full border border-slate-600 px-3 py-2 text-xs font-medium
                               text-slate-100 hover:bg-slate-800"
                    >
                        Dashboard
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button
                            type="submit"
                            class="w-full rounded-full bg-indigo-500 px-3 py-2 text-xs font-semibold text-white
                                   shadow-sm hover:bg-indigo-600"
                        >
                            Log out
                        </button>
                    </form>
                @else
                    @if (Route::has('login'))
                        <a
                            href="{{ route('login') }}"
                            class="block w-full text-center rounded-full border border-slate-600 px-3 py-2 text-xs font-medium
                                   text-slate-100 hover:bg-slate-800"
                        >
                            Log in
                        </a>
                    @endif

                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="mt-3 block w-full text-center rounded-full bg-indigo-500 px-3 py-2 text-xs font-semibold
                                   text-white shadow-sm hover:bg-indigo-600"
                        >
                            Sign up
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</div>

@once
    <script>
        document.addEventListener('change', function (e) {
            const select = e.target.closest('[data-lang-select]');
            if (!select) return;

            const code = select.value;
            const url = `/lang/${code}`;

            // If Livewire is present, do slick swap-without-reload
            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(() => {
                    window.Livewire.dispatch('language-switched', { code });
                });
            } else {
                // Non-Livewire pages (guest/auth): normal redirect
                window.location.href = url;
            }
        });
    </script>
@endonce
