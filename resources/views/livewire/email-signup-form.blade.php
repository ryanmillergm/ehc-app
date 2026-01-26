{{-- resources/views/livewire/email-signup-form.blade.php --}}

@php($tsKey = 'tsEmailSignup_' . $this->getId())

<div>
    {{-- Flash messages --}}
    @if (session()->has('email_signup_success'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            x-transition.opacity.duration.200ms
            class="mb-3 flex items-start justify-between gap-3 rounded-md bg-emerald-50 px-4 py-3 text-emerald-900"
            role="status"
        >
            <div class="text-sm">
                {{ session('email_signup_success') }}
            </div>

            <button
                type="button"
                class="rounded-md p-1 text-emerald-900/60 hover:text-emerald-900 hover:bg-emerald-100"
                @click="show = false"
                aria-label="Dismiss"
            >
                <span class="text-lg leading-none">&times;</span>
            </button>
        </div>
    @endif

    @if (session()->has('email_signup_info'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            x-transition.opacity.duration.200ms
            class="mb-3 flex items-start justify-between gap-3 rounded-md bg-sky-50 px-4 py-3 text-sky-900"
            role="status"
        >
            <div class="text-sm">
                {{ session('email_signup_info') }}
            </div>

            <button
                type="button"
                class="rounded-md p-1 text-sky-900/60 hover:text-sky-900 hover:bg-sky-100"
                @click="show = false"
                aria-label="Dismiss"
            >
                <span class="text-lg leading-none">&times;</span>
            </button>
        </div>
    @endif

    @if ($variant === 'page')
        <form wire:submit.prevent="submit" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <input
                        type="text"
                        wire:model.defer="first_name"
                        placeholder="First name"
                        class="w-full rounded-md border border-slate-300 px-3 py-2"
                    />
                    @error('first_name') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <input
                        type="text"
                        wire:model.defer="last_name"
                        placeholder="Last name"
                        class="w-full rounded-md border border-slate-300 px-3 py-2"
                    />
                    @error('last_name') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <input
                    type="email"
                    wire:model.defer="email"
                    placeholder="Email address"
                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                />
                @error('email') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
                @error('turnstileToken') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- Turnstile (page variant) --}}
            <div class="mt-2" wire:ignore
                 x-data
                 x-init="
                    window.__tsWidgets = window.__tsWidgets || {};
                    window.__tsResetListeners = window.__tsResetListeners || {};

                    const render = () => {
                        if (!window.turnstile) return setTimeout(render, 50);
                        if (window.__tsWidgets['{{ $tsKey }}'] !== undefined) return;

                        window.__tsWidgets['{{ $tsKey }}'] = turnstile.render($refs.ts, {
                            sitekey: '{{ config('services.turnstile.key') }}',
                            callback: (token) => { @this.set('turnstileToken', token) },
                            'expired-callback': () => { @this.set('turnstileToken', null) },
                            'error-callback': () => { @this.set('turnstileToken', null) },
                            theme: 'light',
                        });
                    };

                    render();

                    if (!window.__tsResetListeners['{{ $tsKey }}']) {
                        window.__tsResetListeners['{{ $tsKey }}'] = true;

                        window.addEventListener('turnstile-reset', (e) => {
                            if (e?.detail?.id !== '{{ $tsKey }}') return;

                            const wid = window.__tsWidgets['{{ $tsKey }}'];
                            if (window.turnstile && wid !== undefined) {
                                turnstile.reset(wid);
                            }
                            @this.set('turnstileToken', null);
                        });
                    }
                 "
            >
                <div x-ref="ts"></div>
            </div>

            <button class="rounded-md bg-slate-900 px-5 py-2 font-semibold text-white">
                Sign up
            </button>
        </form>
    @else
        {{-- footer variant --}}
        <form wire:submit.prevent="submit"
              class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
            {{-- Left column: input + turnstile stacked --}}
            <div class="flex-1 min-w-0 space-y-2">
                {{-- Honeypot --}}
                <div class="hidden">
                    <input type="text" wire:model.defer="company" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <input
                        type="email"
                        wire:model.defer="email"
                        placeholder="Email address"
                        class="w-full rounded-md border border-white/10 bg-white/95 px-3 py-2
                               text-slate-900 placeholder:text-slate-500
                               focus:border-white/20 focus:ring-2 focus:ring-white/20"
                    />
                    @error('email') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                    @error('turnstileToken') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                </div>

                {{-- Turnstile (below input) --}}
                <div wire:ignore
                     x-data
                     x-init="
                        window.__tsWidgets = window.__tsWidgets || {};
                        window.__tsResetListeners = window.__tsResetListeners || {};

                        const render = () => {
                            if (!window.turnstile) return setTimeout(render, 50);
                            if (window.__tsWidgets['{{ $tsKey }}'] !== undefined) return;

                            window.__tsWidgets['{{ $tsKey }}'] = turnstile.render($refs.ts, {
                                sitekey: '{{ config('services.turnstile.key') }}',
                                callback: (token) => { @this.set('turnstileToken', token) },
                                'expired-callback': () => { @this.set('turnstileToken', null) },
                                'error-callback': () => { @this.set('turnstileToken', null) },
                                theme: 'dark',
                            });
                        };

                        render();

                        if (!window.__tsResetListeners['{{ $tsKey }}']) {
                            window.__tsResetListeners['{{ $tsKey }}'] = true;

                            window.addEventListener('turnstile-reset', (e) => {
                                if (e?.detail?.id !== '{{ $tsKey }}') return;

                                const wid = window.__tsWidgets['{{ $tsKey }}'];
                                if (window.turnstile && wid !== undefined) {
                                    turnstile.reset(wid);
                                }
                                @this.set('turnstileToken', null);
                            });
                        }
                     "
                >
                    <div x-ref="ts"></div>
                </div>
            </div>

            {{-- Right column: button --}}
            <div class="sm:ml-3 w-full sm:w-auto">
                <button
                    type="submit"
                    class="w-full sm:w-auto rounded-md bg-slate-900 px-5 py-2 font-semibold text-white"
                >
                    Subscribe
                </button>
            </div>
        </form>
    @endif
</div>
