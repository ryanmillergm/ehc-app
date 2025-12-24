<div>
    @if (session()->has('email_signup_success'))
        <div class="mb-3 rounded-md bg-emerald-50 px-4 py-3 text-emerald-800">
            {{ session('email_signup_success') }}
        </div>
    @endif

    <form wire:submit.prevent="submit" class="flex flex-col gap-3 sm:flex-row sm:items-start">
        <div class="flex-1">
            <input
                type="email"
                wire:model.defer="email"
                placeholder="Email address"
                class="w-full rounded-md border border-slate-300 px-3 py-2"
            />
            @error('email') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
        </div>

        <div class="flex-1">
            <input
                type="text"
                wire:model.defer="name"
                placeholder="Name (optional)"
                class="w-full rounded-md border border-slate-300 px-3 py-2"
            />
            @error('name') <div class="mt-1 text-sm text-rose-600">{{ $message }}</div> @enderror
        </div>

        <button
            type="submit"
            class="rounded-md bg-slate-900 px-5 py-2 font-semibold text-white"
        >
            Sign up
        </button>
    </form>
</div>
