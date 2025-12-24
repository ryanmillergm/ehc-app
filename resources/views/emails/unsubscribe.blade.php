<x-guest-layout>
    <div class="mx-auto max-w-xl px-6 py-12">
        <h1 class="text-2xl font-bold text-slate-900">You’re unsubscribed</h1>

        <p class="mt-3 text-slate-700">
            <span class="font-semibold">{{ $email }}</span> will no longer receive emails from us.
        </p>

        <div class="mt-6">
            <a href="{{ route('home') }}" class="inline-flex rounded-full bg-slate-900 px-5 py-2 text-white font-semibold hover:bg-slate-800 transition">
                Back to home
            </a>
        </div>
    </div>
</x-guest-layout>
