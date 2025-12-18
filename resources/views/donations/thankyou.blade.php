<x-layouts.app title="Thank you">
    @php
        $amount = number_format($transaction->amount_dollars, 2);

        $paidAt = $transaction->paid_at
            ? $transaction->paid_at->format('F j, Y g:i A')
            : $transaction->created_at->format('F j, Y g:i A');

        $status = strtoupper($transaction->status ?? 'SUCCEEDED');

        $meta      = is_array($transaction->metadata) ? $transaction->metadata : [];
        $cardBrand = data_get($meta, 'card_brand');
        $cardLast4 = data_get($meta, 'card_last4');

        $reference = 'TX-' . $transaction->id;

        $processorReference = $transaction->payment_intent_id ?? $transaction->charge_id;
    @endphp

    <main class="bg-white text-slate-900">
        <section class="relative overflow-hidden">
            <div class="absolute inset-0">
                <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>
                <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-rose-200/40 blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl"></div>
            </div>

            <div class="relative mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 pt-24 pb-14">
                <div class="max-w-3xl mx-auto text-center">

                    {{-- pills (screen-only) --}}
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-2 no-print">
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-900 text-white px-4 py-1.5 text-xs font-semibold tracking-wide">
                            Bread of Grace Ministries
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                            Sacramento, CA
                        </span>

                        <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-4 py-1.5 text-xs font-semibold">
                            Payment received
                        </span>

                        @if ($transaction->receipt_url)
                            <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 px-4 py-1.5 text-xs font-semibold">
                                Receipt available
                            </span>
                        @endif
                    </div>

                    <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold tracking-tight leading-[1.05] no-print">
                        Thank you for your gift.
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-500 to-rose-700">
                            Seriously.
                        </span>
                    </h1>

                    <p class="mt-4 text-lg text-slate-600 leading-relaxed no-print">
                        We’ve received your donation of <span class="font-extrabold text-slate-900">${{ $amount }}</span>.
                        Your generosity helps meals, essentials, and consistent discipleship happen every week.
                    </p>

                    {{-- RECEIPT CARD --}}
                    <div class="mt-10 text-left">
                        <div class="relative rounded-[2rem] bg-white shadow-lg ring-1 ring-slate-200 overflow-hidden receipt-paper">
                            <div class="px-6 py-5 sm:px-8 bg-gradient-to-r from-rose-50 to-indigo-50 border-b border-slate-200">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <div class="text-xs font-semibold tracking-widest uppercase text-slate-600">Receipt</div>
                                        <div class="mt-1 text-xl font-extrabold text-slate-900">Donation Confirmed</div>
                                        <div class="mt-1 text-sm text-slate-600">Keep this for your records.</div>
                                    </div>

                                    <div class="flex items-center gap-2 no-print">
                                        <span class="inline-flex items-center justify-center rounded-full bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                            {{ $status }}
                                        </span>

                                        <button
                                            type="button"
                                            onclick="window.print()"
                                            class="inline-flex items-center justify-center rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition"
                                        >
                                            Print receipt
                                        </button>
                                    </div>

                                    <div class="hidden print-only">
                                        <span class="text-xs font-semibold text-slate-600">Status:</span>
                                        <span class="text-xs font-semibold text-slate-900">{{ $status }}</span>
                                    </div>
                                </div>
                            </div>

                            <div aria-hidden="true" class="relative">
                                <div class="h-px bg-slate-200"></div>

                                <div class="absolute inset-x-8 -top-[1px] h-[2px] opacity-70"
                                     style="
                                        background-image: radial-gradient(circle, rgba(148,163,184,0.9) 1px, transparent 1.6px);
                                        background-size: 12px 12px;
                                        background-position: 0 0;
                                     ">
                                </div>

                                <div class="absolute -left-3 -top-3 h-6 w-6 rounded-full bg-slate-50 ring-1 ring-slate-200"></div>
                                <div class="absolute -right-3 -top-3 h-6 w-6 rounded-full bg-slate-50 ring-1 ring-slate-200"></div>
                            </div>

                            <div class="p-6 sm:p-8 receipt-body">
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-6">
                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Amount</dt>
                                        <dd class="mt-2 text-3xl font-extrabold text-slate-900">${{ $amount }}</dd>
                                        <dd class="mt-1 text-sm text-slate-500">USD</dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Date</dt>
                                        <dd class="mt-2 text-base font-semibold text-slate-900">{{ $paidAt }}</dd>
                                        <dd class="mt-1 text-sm text-slate-500">Thank you for stepping in.</dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Donor</dt>
                                        <dd class="mt-2 text-base text-slate-900">
                                            @if($transaction->payer_name)
                                                <div class="font-semibold">{{ $transaction->payer_name }}</div>
                                            @endif

                                            @if($transaction->payer_email)
                                                <div class="text-slate-600">{{ $transaction->payer_email }}</div>
                                            @else
                                                <div class="text-slate-500">—</div>
                                            @endif
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Payment method</dt>
                                        <dd class="mt-2 text-base text-slate-900">
                                            @if($cardBrand || $cardLast4)
                                                <span class="font-semibold">{{ strtoupper($cardBrand ?? 'CARD') }}</span>
                                                <span class="text-slate-600">ending in</span>
                                                <span class="font-semibold">{{ $cardLast4 }}</span>
                                            @elseif($transaction->payment_method_id)
                                                <span class="text-slate-600">Card</span>
                                            @else
                                                <span class="text-slate-500">—</span>
                                            @endif
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                                                <div class="text-slate-600">
                                                    <span class="font-semibold text-slate-900">Reference:</span>
                                                    <span class="font-mono break-all">{{ $reference }}</span>

                                                    @if($processorReference)
                                                        <span class="text-slate-400">•</span>
                                                        <span class="text-slate-500">Processor:</span>
                                                        <span class="font-mono break-all text-slate-500">{{ $processorReference }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-slate-600">
                                                    <span class="font-semibold text-slate-900">Type:</span>
                                                    {{ $transaction->type ?? 'one_time' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </dl>

                                <div class="mt-7 flex flex-col sm:flex-row gap-3 no-print">
                                    @if ($transaction->receipt_url)
                                        <a
                                            href="{{ $transaction->receipt_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-slate-900 ring-1 ring-slate-300 hover:bg-slate-50 transition"
                                        >
                                            View Stripe receipt
                                        </a>
                                    @endif

                                    <a
                                        href="{{ route('dashboard') }}"
                                        class="inline-flex items-center justify-center rounded-full bg-rose-700 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-rose-800 transition"
                                    >
                                        View Dashboard
                                    </a>

                                    <a
                                        href="{{ url('/#serve') }}"
                                        class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50 transition"
                                    >
                                        Volunteer with us
                                    </a>
                                </div>

                                <p class="mt-6 text-xs text-slate-500 leading-relaxed text-center no-print">
                                    Your support helps create consistent, steady, week-after-week care — not just a moment, but a pathway.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 flex flex-wrap items-center justify-center gap-2 text-sm no-print">
                        <a href="{{ url('/#visit') }}" class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 transition">
                            Visit Thursday/Sunday • 11am →
                        </a>
                        <a href="{{ url('/#about') }}" class="rounded-full bg-white px-4 py-2 shadow-sm ring-1 ring-slate-200 text-slate-700 hover:bg-slate-50 transition">
                            Learn about our 3-phase pathway →
                        </a>
                    </div>

                </div>
            </div>
        </section>

        <x-footer />

        @once
            <style>
                @media print {
                    @page { margin: 12mm; }

                    nav, footer, .no-print { display: none !important; }

                    body, main { background: #fff !important; }

                    .receipt-paper {
                        box-shadow: none !important;
                        border: 1px solid #CBD5E1 !important;
                        break-inside: avoid;
                        page-break-inside: avoid;
                        width: 100% !important;
                        max-width: none !important;
                    }

                    .receipt-body {
                        padding: 18px !important;
                    }

                    .print-only { display: inline-flex !important; }

                    .receipt-paper * {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }

                .print-only { display: none; }
            </style>
            <script>
                sessionStorage.removeItem('donation_widget_attempt_id');
            </script>
        @endonce
    </main>
</x-layouts.app>
