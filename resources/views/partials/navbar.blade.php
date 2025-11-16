{{-- resources/views/partials/navbar.blade.php --}}

<nav
    id="main-navbar"
    class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-200
           transform transition-transform duration-300"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
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
                    <span class="hidden lg:inline-block text-slate-700">
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
