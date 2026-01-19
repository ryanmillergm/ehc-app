<div class="min-h-[60vh] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-3xl">
        <div class="relative overflow-hidden rounded-2xl border bg-white shadow-sm">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-50 via-white to-gray-50"></div>

            <div class="relative p-6 sm:p-10">
                <div class="flex flex-col sm:flex-row sm:items-start gap-6">
                    <div class="shrink-0">
                        <div class="h-12 w-12 sm:h-14 sm:w-14 rounded-2xl bg-gray-100 flex items-center justify-center">
                            <svg class="h-6 w-6 sm:h-7 sm:w-7 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="flex-1" data-testid="apply-unavailable">
                        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-gray-900"
                            data-testid="apply-unavailable-title">
                            {{ $title }}
                        </h1>

                        <p class="mt-2 text-gray-700 leading-relaxed"
                           data-testid="apply-unavailable-message">
                            {{ $message }}
                        </p>

                        @if($need)
                            <div class="mt-5 rounded-xl bg-gray-50 border p-4">
                                <div class="text-xs uppercase tracking-wide text-gray-500">
                                    Volunteer role
                                </div>
                                <div class="mt-1 text-base sm:text-lg font-medium text-gray-900">
                                    {{ $need->title ?? $need->name ?? $need->slug }}
                                </div>
                            </div>
                        @endif

                        <div class="mt-6 flex flex-col sm:flex-row gap-3">
                            <a href="{{ Route::has('home') ? route('home') : url('/') }}"
                               class="inline-flex justify-center items-center rounded-xl px-4 py-2.5 border border-gray-300 bg-white text-gray-900 hover:bg-gray-50">
                                Back to home
                            </a>

                            @auth
                                <a href="{{ route('dashboard') }}"
                                   class="inline-flex justify-center items-center rounded-xl px-4 py-2.5 bg-black text-white hover:opacity-90">
                                    Dashboard
                                </a>
                            @endauth
                        </div>

                        <p class="mt-6 text-sm text-gray-500">
                            If you believe this is a mistake, please contact support.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
