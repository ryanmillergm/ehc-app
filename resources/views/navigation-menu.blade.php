@php
    use Illuminate\Support\Facades\Auth;
    use Laravel\Jetstream\Jetstream;

    $user = Auth::user();

    $hasCustomPhoto = Jetstream::managesProfilePhotos()
        && ! empty($user?->profile_photo_path);

    // Build initials (first + last) as a fallback
    $nameSource = trim(($user->name ?? '') . ' ' . ($user->first_name ?? ''));
    $parts = preg_split('/\s+/', $nameSource, -1, PREG_SPLIT_NO_EMPTY);

    $initials = '';
    if (! empty($parts)) {
        $initials = mb_substr($parts[0], 0, 1);
        if (count($parts) > 1) {
            $initials .= mb_substr(end($parts), 0, 1);
        }
    } elseif (! empty($user->email)) {
        $initials = mb_substr($user->email, 0, 1);
    }
    $initials = mb_strtoupper($initials ?: 'U');

    $linkBase = 'inline-flex items-center px-3 py-2 text-xs font-semibold border-b-2 border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 transition';
@endphp

<nav x-data="{ open: false }" class="border-b border-slate-200 bg-white/90 backdrop-blur">
    <!-- Primary Navigation Menu -->
    <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20">
        <div class="relative flex h-16 items-center justify-between gap-4">
            {{-- LEFT: logo --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <img
                        src="{{ asset('images/logos/bread-of-grace-logo-clean.png') }}"
                        alt="Bread of Grace Ministries"
                        class="h-8 w-auto sm:h-9"
                    />
                </a>
            </div>

            {{-- RIGHT: teams + user dropdown (desktop only) --}}
            <div class="hidden md:flex items-center gap-4">
                {{-- Teams Dropdown --}}
                @if (Jetstream::hasTeamFeatures())
                    <div class="relative">
                        <x-dropdown align="right" width="60">
                            <x-slot name="trigger">
                                <span class="inline-flex rounded-full shadow-sm">
                                    <button type="button"
                                            class="inline-flex items-center px-3 py-2 border border-slate-200 text-xs leading-4 font-semibold rounded-full text-slate-700 bg-white hover:text-rose-700 hover:border-rose-300 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1 transition">
                                        {{ $user->currentTeam->first_name }}

                                        <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                             viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/>
                                        </svg>
                                    </button>
                                </span>
                            </x-slot>

                            <x-slot name="content">
                                <div class="w-60">
                                    <!-- Team Management -->
                                    <div class="block px-4 py-2 text-xs text-slate-400">
                                        {{ __('Manage Team') }}
                                    </div>

                                    <!-- Team Settings -->
                                    <x-dropdown-link href="{{ route('teams.show', $user->currentTeam->id) }}">
                                        {{ __('Team Settings') }}
                                    </x-dropdown-link>

                                    @can('create', Jetstream::newTeamModel())
                                        <x-dropdown-link href="{{ route('teams.create') }}">
                                            {{ __('Create New Team') }}
                                        </x-dropdown-link>
                                    @endcan

                                    <!-- Team Switcher -->
                                    @if ($user->allTeams()->count() > 1)
                                        <div class="border-t border-slate-200"></div>

                                        <div class="block px-4 py-2 text-xs text-slate-400">
                                            {{ __('Switch Teams') }}
                                        </div>

                                        @foreach ($user->allTeams() as $team)
                                            <x-switchable-team :team="$team" />
                                        @endforeach
                                    @endif
                                </div>
                            </x-slot>
                        </x-dropdown>
                    </div>
                @endif

                {{-- Settings Dropdown --}}
                <div class="relative">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            @if (Jetstream::managesProfilePhotos() && $hasCustomPhoto)
                                {{-- Use uploaded photo --}}
                                <button
                                    class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-rose-400 transition">
                                    <img class="h-8 w-8 rounded-full object-cover"
                                         src="{{ $user->profile_photo_url }}"
                                         alt="{{ $user->first_name }}" />
                                </button>
                            @else
                                {{-- Abstract initials avatar --}}
                                <button
                                    class="flex items-center justify-center h-8 w-8 rounded-full bg-gradient-to-br from-slate-700 via-slate-900 to-slate-800 text-[0.7rem] font-semibold text-slate-50 border border-slate-800/70 shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1">
                                    {{ $initials }}
                                </button>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            <!-- Account Management -->
                            <div class="block px-4 py-2 text-xs text-slate-400">
                                {{ __('Manage Account') }}
                            </div>

                            <x-dropdown-link href="{{ route('profile.show') }}">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <x-dropdown-link href="{{ route('giving.index') }}">
                                {{ __('My Giving') }}
                            </x-dropdown-link>

                            @if (Jetstream::hasApiFeatures())
                                <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                    {{ __('API Tokens') }}
                                </x-dropdown-link>
                            @endif

                            <div class="border-t border-slate-200"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}" x-data>
                                @csrf

                                <x-dropdown-link href="{{ route('logout') }}"
                                                 @click.prevent="$root.submit();">
                                    {{ __('auth.log_out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>

            <!-- CENTER: nav links (desktop only) -->
            <div class="pointer-events-none absolute inset-x-0 flex justify-center">
                <div class="hidden md:flex items-center gap-2 pointer-events-auto">
                    <a href="{{ route('dashboard') }}"
                       class="{{ $linkBase }} {{ request()->routeIs('dashboard') ? 'text-slate-900 border-slate-900' : '' }}">
                        Dashboard
                    </a>

                    <a href="{{ route('giving.index') }}"
                       class="{{ $linkBase }} {{ request()->routeIs('giving.*') ? 'text-slate-900 border-slate-900' : '' }}">
                        My Giving
                    </a>

                    <a href="{{ route('profile.show') }}"
                       class="{{ $linkBase }} {{ request()->routeIs('profile.show') ? 'text-slate-900 border-slate-900' : '' }}">
                        Profile
                    </a>

                    <a href="{{ route('addresses.index') }}"
                       class="{{ $linkBase }} {{ request()->routeIs('addresses.*') ? 'text-slate-900 border-slate-900' : '' }}">
                        Addresses
                    </a>

                    <a href="{{ route('home') }}"
                       class="{{ $linkBase }} {{ request()->routeIs('home.*') ? 'text-slate-900 border-slate-900' : '' }}">
                        Home
                    </a>
                </div>
            </div>

            <!-- Hamburger (mobile only: < md) -->
            <div class="flex items-center md:hidden">
                <button @click="open = ! open"
                        class="inline-flex items-center justify-center rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:bg-slate-100 focus:text-slate-700 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (mobile only: < md) -->
    <div
        x-cloak
        :class="{'block': open, 'hidden': ! open}"
        class="hidden md:hidden bg-white/95 border-t border-slate-200"
    >
        <!-- Profile block -->
        <div class="pt-4 pb-3 border-b border-slate-200">
            <div class="flex items-center px-4">
                @if (Jetstream::managesProfilePhotos() && $hasCustomPhoto)
                    <div class="shrink-0 me-3">
                        <img class="h-10 w-10 rounded-full object-cover"
                             src="{{ $user->profile_photo_url }}"
                             alt="{{ $user->first_name }}" />
                    </div>
                @else
                    <div class="shrink-0 me-3 flex items-center justify-center h-10 w-10 rounded-full bg-gradient-to-br from-slate-700 via-slate-900 to-slate-800 text-sm font-semibold text-slate-50 border border-slate-800/70">
                        {{ $initials }}
                    </div>
                @endif

                <div>
                    <div class="font-medium text-base text-slate-900">{{ $user->first_name }}</div>
                    <div class="font-medium text-sm text-slate-500">{{ $user->email }}</div>
                </div>
            </div>
        </div>

        <!-- All nav links under the profile block -->
        <div class="py-3 space-y-1">
            <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link href="{{ route('giving.index') }}" :active="request()->routeIs('giving.*')">
                {{ __('My Giving') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                {{ __('Profile') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link href="{{ route('addresses.index') }}" :active="request()->routeIs('addresses.*')">
                {{ __('Addresses') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link href="{{ route('home') }}" :active="request()->routeIs('home.*')">
                {{ __('Home') }}
            </x-responsive-nav-link>

            @if (Jetstream::hasApiFeatures())
                <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">
                    {{ __('API Tokens') }}
                </x-responsive-nav-link>
            @endif

            <!-- Authentication -->
            <form method="POST" action="{{ route('logout') }}" x-data>
                @csrf

                <x-responsive-nav-link href="{{ route('logout') }}"
                                       @click.prevent="$root.submit();">
                    {{ __('auth.log_out') }}
                </x-responsive-nav-link>
            </form>

            <!-- Team Management -->
            @if (Jetstream::hasTeamFeatures())
                <div class="border-t border-slate-200"></div>

                <div class="block px-4 py-2 text-xs text-slate-400">
                    {{ __('Manage Team') }}
                </div>

                <x-responsive-nav-link href="{{ route('teams.show', $user->currentTeam->id) }}" :active="request()->routeIs('teams.show')">
                    {{ __('Team Settings') }}
                </x-responsive-nav-link>

                @can('create', Jetstream::newTeamModel())
                    <x-responsive-nav-link href="{{ route('teams.create') }}" :active="request()->routeIs('teams.create')">
                        {{ __('Create New Team') }}
                    </x-responsive-nav-link>
                @endcan

                @if ($user->allTeams()->count() > 1)
                    <div class="border-t border-slate-200"></div>

                    <div class="block px-4 py-2 text-xs text-slate-400">
                        {{ __('Switch Teams') }}
                    </div>

                    @foreach ($user->allTeams() as $team)
                        <x-switchable-team :team="$team" component="responsive-nav-link" />
                    @endforeach
                @endif
            @endif
        </div>
    </div>
</nav>
