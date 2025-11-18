<x-layouts.app title="Thank you">
    <main class="pt-24 pb-16">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4">
            <h1 class="text-2xl font-semibold text-slate-900">
                Thank you for your gift!
            </h1>

            <p class="text-sm text-slate-600">
                We’ve received your donation of
                <span class="font-semibold">${{ number_format($transaction->amount_dollars, 2) }}</span>.
            </p>

            @if ($transaction->receipt_url)
                <a
                    href="{{ $transaction->receipt_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center rounded-full bg-indigo-600 px-4 py-2
                           text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                >
                    View receipt
                </a>
            @endif
        </div>
    </main>
</x-layouts.app>
