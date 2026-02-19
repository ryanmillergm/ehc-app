{{-- resources/views/emails/unsubscribe.blade.php --}}
<x-layouts.app
    title="Unsubscribed"
    meta-title="Email Unsubscribe | Bread of Grace Ministries"
    meta-description="Your email unsubscribe request has been processed."
    :canonical-url="url()->current()"
    :meta-robots="config('seo.robots.noindex')"
>
    <div class="mx-auto max-w-xl px-6 py-12">
        <h1 class="text-2xl font-bold text-slate-900">You’re unsubscribed</h1>

        <p class="mt-3 text-slate-700">
            <span class="font-semibold">{{ $email }}</span>

            @if (!empty($message))
                — {{ $message }}
            @else
                will no longer receive emails from us.
            @endif
        </p>

        <div class="mt-6">
            <a href="{{ url('/') }}"
               class="inline-flex rounded-full bg-slate-900 px-5 py-2 text-white font-semibold hover:bg-slate-800 transition">
                Back to home
            </a>
        </div>
    </div>
</x-layouts.app>
