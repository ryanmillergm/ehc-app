{{-- resources/views/donations/thankyou-subscription.blade.php --}}
<x-layouts.app title="Thank you">
    <main class="pt-24 pb-16">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4">
            <h1 class="text-2xl font-semibold text-slate-900">
                Thank you for your monthly gift!
            </h1>

            <p class="text-sm text-slate-600">
                We’ve set up your recurring donation of
                <span class="font-semibold">
                    ${{ number_format($pledge->amount_dollars ?? ($pledge->amount_cents / 100), 2) }}
                </span>
                per month.
            </p>

            @if (! empty($pledge->latest_invoice_id))
                <p class="text-xs text-slate-500">
                    Latest Stripe invoice ID: {{ $pledge->latest_invoice_id }}
                </p>
            @endif

            @if (! empty($subscriptionTransaction?->receipt_url))
                <a
                    href="{{ $subscriptionTransaction->receipt_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center rounded-full bg-indigo-600 px-4 py-2
                           text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                >
                    View Stripe receipt
                </a>
            @endif

            <a
                href="{{ route('home') }}"
                class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2
                       text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
            >
                Back to home
            </a>
        </div>
    </main>
</x-layouts.app>
