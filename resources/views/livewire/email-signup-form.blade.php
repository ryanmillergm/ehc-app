@php($tsKey = 'tsEmailSignup_' . $this->getId() . '_' . $this->variant)

<div>
    @if ($bannerType && $bannerMessage)
        <div
            wire:key="email-signup-banner-{{ $tsKey }}"
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            x-transition.opacity.duration.200ms
            class="mb-3 flex items-start justify-between gap-3 rounded-md px-4 py-3 text-sm
                {{ $bannerType === 'success' ? 'bg-emerald-50 text-emerald-900' : 'bg-sky-50 text-sky-900' }}"
            role="status"
        >
            <div>{{ $bannerMessage }}</div>

            <button
                type="button"
                class="rounded-md p-1 opacity-60 hover:opacity-100"
                @click="show = false"
                aria-label="Dismiss"
            >
                <span class="text-lg leading-none">&times;</span>
            </button>
        </div>
    @endif

    @if ($variant === 'page')
        {{-- PAGE VARIANT --}}
        <form wire:submit.prevent="submit" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <input type="text" wire:model.defer="first_name" placeholder="First name"
                        class="w-full rounded-md border border-slate-300 px-3 py-2" />
                    @error('first_name') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <input type="text" wire:model.defer="last_name" placeholder="Last name"
                        class="w-full rounded-md border border-slate-300 px-3 py-2" />
                    @error('last_name') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <input type="email" wire:model.defer="email" placeholder="Email address"
                    class="w-full rounded-md border border-slate-300 px-3 py-2" />
                @error('email') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
                @error('turnstileToken') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            {{-- Turnstile (page) --}}
            <div
                class="max-w-full overflow-x-auto"
                wire:ignore
                wire:key="{{ $tsKey }}"
                x-data="{
                    key: '{{ $tsKey }}',
                    theme: 'light',
                    ensure() {
                        window.__tsWidgets = window.__tsWidgets || {};

                        const mount = () => {
                            if (!window.turnstile) return setTimeout(mount, 50);

                            const hasIframe = this.$refs.ts && this.$refs.ts.querySelector('iframe');

                            if (!hasIframe) {
                                this.$refs.ts.innerHTML = '';

                                window.__tsWidgets[this.key] = turnstile.render(this.$refs.ts, {
                                    sitekey: '{{ config('services.turnstile.key') }}',
                                    callback: (token) => { $wire.set('turnstileToken', token, true) },
                                    'expired-callback': () => { $wire.set('turnstileToken', null, true) },
                                    'error-callback': () => { $wire.set('turnstileToken', null, true) },
                                    theme: this.theme,
                                });
                            }
                        };

                        mount();
                    },
                    reset() {
                        window.__tsWidgets = window.__tsWidgets || {};
                        const wid = window.__tsWidgets[this.key];

                        if (window.turnstile && wid !== undefined) turnstile.reset(wid);
                        else this.ensure();

                        $wire.set('turnstileToken', null, true);
                    }
                }"
                x-init="ensure()"
                x-on:turnstile-reset.window="if ($event?.detail?.id === key) reset();"
            >
                <div class="inline-block" x-ref="ts"></div>
            </div>

            <button
                type="submit"
                class="rounded-md bg-slate-900 px-5 py-2 font-semibold text-white
                       hover:bg-slate-800 active:bg-slate-900 transition"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">Sign up</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </button>
        </form>

    @else
        {{-- FOOTER VARIANT --}}
        <form wire:submit.prevent="submit" class="space-y-2">
            {{-- Honeypot --}}
            <div class="hidden">
                <input type="text" wire:model.defer="company" tabindex="-1" autocomplete="off">
            </div>

            {{-- Row 1: email + button --}}
            <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 items-start">
                <div class="min-w-0">
                    <input
                        type="email"
                        wire:model.defer="email"
                        placeholder="Email address"
                        class="w-full rounded-md border border-white/10 bg-white/95 px-3 py-2
                               text-slate-900 placeholder:text-slate-500
                               focus:border-white/20 focus:ring-2 focus:ring-white/20"
                    />
                </div>

                <div>
                    <button
                        type="submit"
                        class="w-full sm:w-auto rounded-md bg-slate-900 px-5 py-2 font-semibold text-white
                               hover:bg-slate-800 active:bg-slate-900 transition"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                    >
                        <span wire:loading.remove wire:target="submit">Subscribe</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                    </button>
                </div>
            </div>

            {{-- Errors --}}
            <div>
                @error('email') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
                @error('turnstileToken') <div class="mt-1 text-sm text-rose-300">{{ $message }}</div> @enderror
            </div>

            {{-- Turnstile (footer) --}}
            <div
                class="max-w-full overflow-x-auto"
                wire:ignore
                wire:key="{{ $tsKey }}"
                x-data="{
                    key: '{{ $tsKey }}',
                    theme: 'dark',
                    ensure() {
                        window.__tsWidgets = window.__tsWidgets || {};

                        const mount = () => {
                            if (!window.turnstile) return setTimeout(mount, 50);

                            const hasIframe = this.$refs.ts && this.$refs.ts.querySelector('iframe');

                            if (!hasIframe) {
                                this.$refs.ts.innerHTML = '';

                                window.__tsWidgets[this.key] = turnstile.render(this.$refs.ts, {
                                    sitekey: '{{ config('services.turnstile.key') }}',
                                    callback: (token) => { $wire.set('turnstileToken', token, true) },
                                    'expired-callback': () => { $wire.set('turnstileToken', null, true) },
                                    'error-callback': () => { $wire.set('turnstileToken', null, true) },
                                    theme: this.theme,
                                });
                            }
                        };

                        mount();
                    },
                    reset() {
                        window.__tsWidgets = window.__tsWidgets || {};
                        const wid = window.__tsWidgets[this.key];

                        if (window.turnstile && wid !== undefined) turnstile.reset(wid);
                        else this.ensure();

                        $wire.set('turnstileToken', null, true);
                    }
                }"
                x-init="ensure()"
                x-on:turnstile-reset.window="if ($event?.detail?.id === key) reset();"
            >
                <div class="inline-block" x-ref="ts"></div>
            </div>
        </form>
    @endif
</div>
