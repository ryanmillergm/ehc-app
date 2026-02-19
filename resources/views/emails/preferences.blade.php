<x-layouts.app
    title="Email Preferences"
    meta-title="Email Preferences | Bread of Grace Ministries"
    meta-description="Manage your Bread of Grace Ministries email preferences."
    :canonical-url="url('/email-preferences/' . $token)"
    :meta-robots="config('seo.robots.noindex')"
>
    <div class="mx-auto max-w-3xl px-6 py-12">
        <h1 class="text-3xl font-extrabold text-slate-900">Email preferences</h1>
        <p class="mt-3 text-slate-600">
            Update what you receive. Transactional emails (receipts, confirmations, etc.) are required.
        </p>

        <div class="mt-8 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <livewire:email-preferences-public-form :token="$token" />
        </div>

        <div class="mt-6 text-sm text-slate-500">
            <a href="{{ url('/') }}" class="underline">Back to home</a>
        </div>
    </div>
</x-layouts.app>
