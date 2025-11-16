{{-- resources/views/partials/navbar.blade.php --}}
<nav
    id="main-navbar"
    class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur border-b border-slate-200
           transform transition-transform duration-300"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-[auto_1fr_auto] items-center h-16 gap-4">
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

            {{-- Center: Nav links --}}
            <div class="flex justify-center">
                <div class="flex items-center gap-6 text-sm font-medium text-slate-700">
                    <a href="{{ url('/') }}" class="hover:text-indigo-600 transition-colors">Home</a>
                    <a href="{{ url('/pages') }}" class="hover:text-indigo-600 transition-colors">Pages</a>
                    <a href="{{ url('/children') }}" class="hover:text-indigo-600 transition-colors">Children</a>
                    <a href="{{ url('/teams') }}" class="hover:text-indigo-600 transition-colors">Teams</a>
                </div>
            </div>

            {{-- Right: Auth / user --}}
            <div class="flex items-center justify-end gap-3 text-sm">
                @auth
                    <span class="hidden sm:inline-block text-slate-700">
                        Hi, {{ Auth::user()->name }}
                    </span>

                    <a href="{{ route('dashboard') }}"
                       class="hidden sm:inline-flex items-center rounded-full border border-slate-200
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
                           class="hidden sm:inline-flex items-center rounded-full bg-indigo-600 px-3 py-1.5
                                  text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
                            Sign up
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>
