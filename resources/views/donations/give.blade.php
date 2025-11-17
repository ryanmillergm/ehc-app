{{-- Embeds resources\views\components\donation-widget.blade.php --}}
<x-layouts.app title="Give">
    <main class="pt-24 pb-16">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-slate-900 mb-4">
                Support the ministry
            </h1>

            <p class="text-sm text-slate-600 mb-6">
                Your generosity helps us continue the work. Choose an amount and frequency below.
            </p>

            Widget:
            <x-donation-widget />
        </div>
    </main>
</x-layouts.app>
