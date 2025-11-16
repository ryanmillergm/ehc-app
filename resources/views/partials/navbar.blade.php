{{-- resources/views/partials/navbar.blade.php --}}

{{-- Top navbar (desktop + mobile hamburger) --}}
<nav
    id="main-navbar"
    class="fixed top-0 inset-x-0 z-40 bg-white/80 backdrop-blur border-b border-slate-200
           transform transition-transform duration-300"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Left: Logo --}}
            <div class="flex items-center">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-lg">
                        LOGO
                    </div>
                    <span class="hidden sm:inline-block text-base font-semibold text-slate-900">
                        {{ config('app.name', 'Laravel') }}
                    </span>
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

            {{-- Right: Auth / user (desktop) --}}
            <div class="hidden md:flex items-center justify-end gap-3 text-sm">
                @auth
                    <span class="hidden sm:inline-block text-slate-700">
                        Hi, {{ Auth::user()->name }}
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

            {{-- Mobile hamburger (shows on < md) --}}
            <button
                id="mobile-menu-toggle"
                type="button"
                class="md:hidden inline-flex items-center justify-center p-2 rounded-full 
                       bg-white/80 shadow-sm focus:outline-none focus:ring-indigo-500"
                aria-label="Toggle navigation"
                data-state="closed"
            >
                <div class="navbar-hamburger-icon">
                    <span class="navbar-hamburger-line navbar-hamburger-line-top"></span>
                    <span class="navbar-hamburger-line navbar-hamburger-line-middle"></span>
                    <span class="navbar-hamburger-line navbar-hamburger-line-bottom"></span>
                </div>
            </button>
        </div>
    </div>
</nav>

{{-- Full-screen mobile overlay menu (sibling of nav, so it can cover everything) --}}
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
        <div class="flex items-center justify-between h-16 px-4 border-b border-slate-700">
            <span class="font-semibold text-base">
                {{ config('app.name', 'Laravel') }}
            </span>

            <button
                id="mobile-menu-close"
                type="button"
                class="p-2 rounded-full hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                aria-label="Close menu"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 5l10 10M15 5L5 15" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
            {{-- Mobile nav links --}}
            <nav class="space-y-2 text-sm font-medium">
                <a href="{{ url('/') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Home</a>
                <a href="{{ url('/pages') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Pages</a>
                <a href="{{ url('/children') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Children</a>
                <a href="{{ url('/teams') }}" class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">Teams</a>
            </nav>

            {{-- Mobile auth section --}}
            <div class="pt-6 border-t border-slate-800/60 space-y-3 text-sm">
                @auth
                    <p class="text-slate-300 text-xs uppercase tracking-wide">Signed in as</p>
                    <p class="font-semibold">{{ Auth::user()->name }}</p>

                    <a href="{{ route('dashboard') }}"
                       class="mt-2 inline-flex w-full items-center justify-center rounded-full border border-slate-600
                              px-3 py-2 text-xs font-semibold hover:bg-slate-800">
                        Dashboard
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="mt-2 inline-flex w-full items-center justify-center rounded-full bg-indigo-500
                                   px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-600"
                        >
                            Log out
                        </button>
                    </form>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}"
                           class="inline-flex w-full items-center justify-center rounded-full border border-slate-600
                                  px-3 py-2 text-xs font-semibold hover:bg-slate-800">
                            Log in
                        </a>
                    @endif

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="inline-flex w-full items-center justify-center rounded-full bg-indigo-500
                                  px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-600">
                            Sign up
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</div>
