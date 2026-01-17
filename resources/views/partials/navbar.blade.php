@php
    use App\Models\Language;
    use Illuminate\Support\Facades\Auth;

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
                    {{-- <a href="{{ url('/') }}"
                       class="hover:text-indigo-600 transition-colors {{ request()->routeIs('home') ? 'text-indigo-600' : '' }}">
                        Home
                    </a> --}}
                    {{-- Commenting out until pages is complete --}}
                    {{-- <a href="{{ url('/pages') }}"
                       class="hover:text-indigo-600 transition-colors {{ request()->is('pages*') ? 'text-indigo-600' : '' }}">
                        Pages
                    </a>
                    <a href="{{ url('/children') }}"
                       class="hover:text-indigo-600 transition-colors {{ request()->is('children*') ? 'text-indigo-600' : '' }}">
                        Children
                    </a>
                    <a href="{{ url('/teams') }}"
                       class="hover:text-indigo-600 transition-colors {{ request()->is('teams*') ? 'text-indigo-600' : '' }}">
                        Teams
                    </a> --}}
                </div>
            </div>

            {{-- Right side (desktop): language picker + donate + auth / user menu --}}
            <div class="hidden md:flex items-center justify-end gap-4 text-sm">
                {{-- Language picker --}}
                <div class="relative">
                    <label for="lang-select" class="sr-only">Language</label>
                    <select
                        id="lang-select"
                        data-lang-select
                        data-lang-toast="{{ __('flash-messages.language_updated') }}"
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

                {{-- Donate CTA --}}
                <a
                    href="{{ route('donations.show') }}"
                    class="relative inline-flex items-center gap-2 rounded-full bg-rose-600 px-4 py-1.5
                           text-xs font-semibold text-white shadow-sm hover:bg-rose-700
                           focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1"
                >
                    <span class="relative flex h-5 w-5 items-center justify-center heart-thump">
                        {{-- pulsing halo --}}
                        <span class="absolute inline-flex h-5 w-5 rounded-full bg-rose-300/60 animate-ping"></span>

                        {{-- solid heart badge --}}
                        <span class="relative flex h-5 w-5 items-center justify-center rounded-full bg-white text-rose-600">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/>
                            </svg>
                        </span>
                    </span>

                    <span>Donate</span>
                </a>

                @auth
                    @php($user = Auth::user())
                    {{-- User dropdown --}}
                    <div class="relative">
                        <button
                            id="user-menu-toggle"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white
                                   px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm
                                   hover:border-indigo-300 hover:bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
                            aria-haspopup="true"
                            aria-expanded="false"
                        >
                            @if ($user->navbar_photo_url ?? false)
                                <img
                                    src="{{ $user->navbar_photo_url }}"
                                    alt="{{ $user->full_name }}"
                                    class="h-7 w-7 rounded-full object-cover"
                                />
                            @else
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full
                                           bg-gradient-to-br from-slate-700 via-slate-900 to-slate-800
                                           text-[0.7rem] font-semibold text-slate-50"
                                >
                                    {{ $user->initials ?? 'U' }}
                                </span>
                            @endif

                            <span class="hidden lg:flex flex-col text-left leading-tight">
                                <span class="text-xs font-semibold text-slate-800">
                                    {{ $user->first_name }}
                                </span>
                            </span>

                            <svg
                                class="ml-1 h-3.5 w-3.5 text-slate-400"
                                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                            >
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div
                            id="user-menu-panel"
                            class="absolute right-0 mt-2 w-60 origin-top-right rounded-xl bg-white py-2 shadow-lg
                                   ring-1 ring-slate-900/5 opacity-0 scale-95 pointer-events-none transform
                                   transition duration-150 ease-out"
                        >
                            <div class="px-4 pb-3 border-b border-slate-100 mb-1">
                                <p class="text-xs font-semibold text-slate-900">
                                    {{ $user->full_name }}
                                </p>
                                <p class="mt-0.5 text-[0.7rem] text-slate-500 truncate">
                                    {{ $user->email }}
                                </p>
                            </div>

                            <a
                                href="{{ route('dashboard') }}"
                                class="flex items-center px-4 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                            >
                                Dashboard
                            </a>

                            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                @csrf
                                <button
                                    type="submit"
                                    class="flex w-full items-center px-4 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                >
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    {{-- Guest --}}
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
                <span class="text-sm font-semibold">Bread of Grace Ministries</span>
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
            @auth
                @php($user = Auth::user())
                {{-- User Dropdown Menu on Desktop --}}
                <div class="flex items-center gap-3 pb-4 border-b border-slate-800/60">
                    @if ($user->navbar_photo_url ?? false)
                        <img
                            src="{{ $user->navbar_photo_url }}"
                            alt="{{ $user->full_name }}"
                            class="h-10 w-10 rounded-full object-cover"
                        />
                    @else
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full
                                   bg-gradient-to-br from-slate-700 via-slate-900 to-slate-800
                                   text-sm font-semibold text-slate-50 border border-slate-800/70"
                        >
                            {{ $user->initials ?? 'U' }}
                        </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-50 truncate">
                            {{ $user->full_name }}
                        </p>
                        <p class="text-xs text-slate-400 truncate">
                            {{ $user->email }}
                        </p>
                    </div>

                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center rounded-full border border-slate-700 px-3 py-1
                               text-[0.7rem] font-medium text-slate-100 hover:bg-slate-800"
                    >
                        Dashboard
                    </a>
                </div>
            @endauth

            {{-- Mobile language picker --}}
            <div>
                <label for="lang-select-mobile" class="block text-xs text-slate-300 mb-2">
                    Language
                </label>

                <div class="relative">
                    <select
                        id="lang-select-mobile"
                        data-lang-select
                        data-lang-toast="{{ __('flash-messages.language_updated') }}"
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

            {{-- Mobile Donate CTA --}}
            <div>
                <a
                    href="{{ route('donations.show') }}"
                    class="mt-2 flex items-center justify-center gap-2 rounded-full bg-rose-600 px-4 py-2
                           text-xs font-semibold text-white shadow-sm hover:bg-rose-700
                           focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                >
                    <span class="relative flex h-5 w-5 items-center justify-center heart-thump">
                        <span class="absolute inline-flex h-5 w-5 rounded-full bg-rose-300/60 animate-ping"></span>
                        <span class="relative flex h-5 w-5 items-center justify-center rounded-full bg-white text-rose-600">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/>
                            </svg>
                        </span>
                    </span>
                    <span>Donate</span>
                </a>
            </div>

            {{-- Mobile nav links --}}
            <nav class="space-y-1 text-sm">
                <a href="{{ url('/') }}"
                   class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">
                    Home
                </a>
                {{-- Commenting out until pages are done --}}
                {{-- <a href="{{ url('/pages') }}"
                   class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">
                    Pages
                </a>
                <a href="{{ url('/children') }}"
                   class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">
                    Children
                </a>
                <a href="{{ url('/teams') }}"
                   class="block py-2 border-b border-slate-800/60 hover:text-indigo-300">
                    Teams
                </a> --}}
            </nav>

            <div class="pt-4 border-t border-slate-800/60">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
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
    <style>
        /* Thumping heart animation (scale in & out) */
        @keyframes heart-thump {
            0%, 100% {
                transform: scale(1);
            }
            20% {
                transform: scale(1.2);
            }
            35% {
                transform: scale(1.05);
            }
            55% {
                transform: scale(1.2);
            }
            75% {
                transform: scale(1.05);
            }
        }

        .heart-thump {
            animation: heart-thump 1.3s ease-in-out infinite;
            transform-origin: center;
        }
    </style>

    <script>
        // ---- Language select (desktop + mobile) ----
        document.addEventListener('change', function (e) {
            const select = e.target.closest('[data-lang-select]');
            if (!select) return;

            const code = select.value;
            const url  = `/lang/${code}`;

            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                })
                .then(async (res) => {
                    let payload = null;
                    try { payload = await res.json(); } catch (_) {}

                    const message = payload?.message ?? 'Language updated.';
                    const style   = payload?.style ?? 'success';

                    // Jetstream banner
                    window.dispatchEvent(new CustomEvent('banner-message', {
                        detail: { style, message }
                    }));

                    window.Livewire.dispatch('language-switched', { code });
                })
                .catch(() => {
                    window.dispatchEvent(new CustomEvent('banner-message', {
                        detail: { style: 'danger', message: 'Could not switch language. Please try again.' }
                    }));
                });

                return;
            }

            // Non-Livewire pages: normal redirect (banner comes from session)
            window.location.href = url;
        });

        // ---- Navbar (mobile menu + user dropdown) ----
        function initBreadOfGraceNavbar() {
            // Prevent wiring twice if Livewire / Turbo re-fires this
            if (window.__bofgNavbarInit) return;
            window.__bofgNavbarInit = true;

            const toggle   = document.getElementById('mobile-menu-toggle');
            const closeBtn = document.getElementById('mobile-menu-close');
            const overlay  = document.getElementById('mobile-menu-overlay');
            const panel    = document.getElementById('mobile-menu-panel');

            if (toggle && overlay && panel) {
                const openMenu = () => {
                    overlay.classList.remove('opacity-0', 'pointer-events-none');
                    overlay.classList.add('opacity-100');
                    panel.classList.remove('translate-x-full');
                };

                const closeMenu = () => {
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                    overlay.classList.remove('opacity-100');
                    panel.classList.add('translate-x-full');
                };

                toggle.addEventListener('click', () => {
                    const isOpen = overlay.classList.contains('opacity-100');
                    if (isOpen) {
                        closeMenu();
                    } else {
                        openMenu();
                    }
                });

                if (closeBtn) {
                    closeBtn.addEventListener('click', closeMenu);
                }

                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        closeMenu();
                    }
                });
            }

            // Desktop user menu dropdown
            const userToggle = document.getElementById('user-menu-toggle');
            const userPanel  = document.getElementById('user-menu-panel');

            if (userToggle && userPanel) {
                let open = false;

                const openClasses   = ['opacity-100', 'scale-100', 'pointer-events-auto'];
                const closedClasses = ['opacity-0', 'scale-95', 'pointer-events-none'];

                const setUserOpen = (value) => {
                    open = value;

                    if (open) {
                        userPanel.classList.remove(...closedClasses);
                        userPanel.classList.add(...openClasses);
                        userToggle.setAttribute('aria-expanded', 'true');
                    } else {
                        userPanel.classList.remove(...openClasses);
                        userPanel.classList.add(...closedClasses);
                        userToggle.setAttribute('aria-expanded', 'false');
                    }
                };

                // Start closed
                setUserOpen(false);

                userToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    setUserOpen(!open);
                });

                document.addEventListener('click', (e) => {
                    if (!open) return;
                    if (!userPanel.contains(e.target) && !userToggle.contains(e.target)) {
                        setUserOpen(false);
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        setUserOpen(false);
                    }
                });
            }
        }

        // Run after DOM ready
        document.addEventListener('DOMContentLoaded', initBreadOfGraceNavbar);

        // If Livewire does SPA-style navigation, re-run after navigation
        document.addEventListener('livewire:navigated', initBreadOfGraceNavbar);
    </script>
@endonce
