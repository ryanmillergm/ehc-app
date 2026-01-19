<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Page not found</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-950 text-white">
    <div class="relative isolate min-h-screen">
        {{-- Background --}}
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 left-1/2 -translate-x-1/2 h-[32rem] w-[32rem] rounded-full bg-purple-500/20 blur-3xl"></div>
            <div class="absolute -bottom-48 right-1/3 h-[28rem] w-[28rem] rounded-full bg-blue-500/20 blur-3xl"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-gray-950 via-gray-950 to-black"></div>
        </div>

        <div class="relative mx-auto max-w-5xl px-4 py-14 sm:py-20">
            <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                {{-- Left: copy --}}
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-sm text-white/80">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                        Lost in the routing wilderness
                    </div>

                    <h1 class="mt-5 text-4xl sm:text-5xl font-semibold tracking-tight">
                        404 — Page not found
                    </h1>

                    <p class="mt-4 text-base sm:text-lg text-white/75 leading-relaxed">
                        That URL doesn’t exist (or it moved). The universe is huge; websites are… doing their best.
                    </p>

                    <div class="mt-7 flex flex-col sm:flex-row gap-3">
                        <a href="{{ Route::has('home') ? route('home') : url('/') }}"
                           class="inline-flex justify-center items-center rounded-xl bg-white text-gray-900 px-5 py-3 font-medium hover:opacity-95">
                            Back to home
                        </a>

                        @auth
                            <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}"
                               class="inline-flex justify-center items-center rounded-xl border border-white/15 bg-white/5 px-5 py-3 font-medium hover:bg-white/10">
                                Dashboard
                            </a>
                        @endauth
                    </div>

                    <div class="mt-8 rounded-xl border border-white/10 bg-white/5 p-4">
                        <div class="text-xs uppercase tracking-wide text-white/60">Requested URL</div>
                        <div class="mt-1 font-mono text-sm text-white/85 break-all">
                            {{ request()->path() }}
                        </div>
                    </div>
                </div>

                {{-- Right: big "404" card --}}
                <div class="lg:justify-self-end">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 sm:p-10 shadow-[0_20px_80px_-40px_rgba(0,0,0,0.8)]">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-white/70">Status</div>
                            <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/75">
                                HTTP 404
                            </div>
                        </div>

                        <div class="mt-6 text-[5rem] sm:text-[7rem] leading-none font-semibold tracking-tight">
                            404
                        </div>

                        <p class="mt-4 text-white/70 leading-relaxed">
                            Try the navigation, go back, or head home. No judgment. We’ve all clicked weird links.
                        </p>

                        <div class="mt-6 grid grid-cols-2 gap-3 text-sm text-white/75">
                            <div class="rounded-xl border border-white/10 bg-black/20 p-3">
                                <div class="text-white/55">Tip</div>
                                <div class="mt-1">Check spelling</div>
                            </div>
                            <div class="rounded-xl border border-white/10 bg-black/20 p-3">
                                <div class="text-white/55">Tip</div>
                                <div class="mt-1">Try the menu</div>
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-white/45">
                        {{ config('app.name') }} · {{ now()->format('Y') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
