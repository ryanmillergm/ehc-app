<div>
    @if (session()->has('email_signup_success'))
        <div class="mb-3 rounded-md bg-emerald-50 px-4 py-3 text-emerald-800">
            {{ session('email_signup_success') }}
        </div>
    @endif

    @if ($variant === 'page')
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
            </div>

            <button class="rounded-md bg-slate-900 px-5 py-2 font-semibold text-white">
                Sign up
            </button>
        </form>
    @else
        {{-- footer: keep it simple; you can restyle later --}}
        <form wire:submit.prevent="submit" class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="flex-1">
                <input type="email" wire:model.defer="email" placeholder="Email address"
                       class="w-full rounded-md border border-slate-300 px-3 py-2" />
                @error('email') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <button class="rounded-md bg-slate-900 px-5 py-2 font-semibold text-white">
                Subscribe
            </button>
        </form>
    @endif
</div>
