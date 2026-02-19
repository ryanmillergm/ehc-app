{{-- resources/views/donations/thankyou-subscription.blade.php --}}
<x-layouts.app
    title="Thank you"
    meta-title="Monthly Donation Receipt | Bread of Grace Ministries"
    meta-description="Thank you for becoming a monthly partner with Bread of Grace Ministries."
    :canonical-url="url('/donations/thank-you-subscription')"
    :meta-robots="config('seo.robots.noindex')"
>
    @php
        $amount = number_format($pledge->amount_dollars ?? ($pledge->amount_cents / 100), 2);

        $status = strtoupper($pledge->status ?? 'ACTIVE');

        $createdAt = $pledge->created_at
            ? $pledge->created_at->format('F j, Y g:i A')
            : now()->format('F j, Y g:i A');

        $donorName  = $pledge->donor_name ?? (auth()->user()?->name ?? null);
        $donorEmail = $pledge->donor_email ?? (auth()->user()?->email ?? null);

        // Donor-facing reference
        $reference = 'PL-' . $pledge->id;

        // Stripe refs (useful for support, optional)
        $subscriptionId = $pledge->stripe_subscription_id ?? null;
        $latestInvoice  = $pledge->latest_invoice_id ?? null;

        $receiptUrl = $subscriptionTransaction?->receipt_url;
    @endphp

    <main class="bg-white text-slate-900">
        <section class="relative overflow-hidden">
            {{-- Background --}}
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

                        <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 px-4 py-1.5 text-xs font-semibold">
                            Monthly gift active
                        </span>

                        @if ($receiptUrl)
                            <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 px-4 py-1.5 text-xs font-semibold">
                                Receipt available
                            </span>
                        @endif
                    </div>

                    <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold tracking-tight leading-[1.05] no-print">
                        Thank you for your monthly gift.
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-500 to-rose-700">
                            For real.
                        </span>
                    </h1>

                    <p class="mt-4 text-lg text-slate-600 leading-relaxed no-print">
                        Your recurring support of <span class="font-extrabold text-slate-900">${{ $amount }}</span> helps keep meals,
                        essentials, and consistent discipleship happening week after week.
                    </p>

                    {{-- RECEIPT "PAPER" CARD --}}
                    <div class="mt-10 text-left">
                        <div class="relative rounded-[2rem] bg-white shadow-lg ring-1 ring-slate-200 overflow-hidden receipt-paper">
                            {{-- header strip --}}
                            <div class="px-6 py-5 sm:px-8 bg-gradient-to-r from-rose-50 to-indigo-50 border-b border-slate-200">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <div class="text-xs font-semibold tracking-widest uppercase text-slate-600">Receipt</div>
                                        <div class="mt-1 text-xl font-extrabold text-slate-900">Recurring Donation Set Up</div>
                                        <div class="mt-1 text-sm text-slate-600">Keep this for your records.</div>
                                    </div>

                                    <div class="flex items-center gap-2 no-print">
                                        <span class="inline-flex items-center justify-center rounded-full bg-indigo-50 px-4 py-2 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100">
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

                                    {{-- print-only status --}}
                                    <div class="hidden print-only">
                                        <span class="text-xs font-semibold text-slate-600">Status:</span>
                                        <span class="text-xs font-semibold text-slate-900">{{ $status }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- perforated tear line --}}
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

                            {{-- body --}}
                            <div class="p-6 sm:p-8 receipt-body">
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-6">
                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Monthly amount</dt>
                                        <dd class="mt-2 text-3xl font-extrabold text-slate-900">${{ $amount }}</dd>
                                        <dd class="mt-1 text-sm text-slate-500">USD • billed monthly</dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Started</dt>
                                        <dd class="mt-2 text-base font-semibold text-slate-900">{{ $createdAt }}</dd>
                                        <dd class="mt-1 text-sm text-slate-500">Thank you for stepping in.</dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Donor</dt>
                                        <dd class="mt-2 text-base text-slate-900">
                                            @if($donorName)
                                                <div class="font-semibold">{{ $donorName }}</div>
                                            @endif

                                            @if($donorEmail)
                                                <div class="text-slate-600">{{ $donorEmail }}</div>
                                            @else
                                                <div class="text-slate-500">—</div>
                                            @endif
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-semibold tracking-widest uppercase text-slate-500">Schedule</dt>
                                        <dd class="mt-2 text-base text-slate-900">
                                            <span class="font-semibold">Monthly</span>
                                            <span class="text-slate-600">recurring donation</span>
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                                                <div class="text-slate-600">
                                                    <span class="font-semibold text-slate-900">Reference:</span>
                                                    <span class="font-mono break-all">{{ $reference }}</span>

                                                    @if($subscriptionId)
                                                        <span class="text-slate-400">•</span>
                                                        <span class="text-slate-500">Subscription:</span>
                                                        <span class="font-mono break-all text-slate-500">{{ $subscriptionId }}</span>
                                                    @endif

                                                    @if($latestInvoice)
                                                        <span class="text-slate-400">•</span>
                                                        <span class="text-slate-500">Invoice:</span>
                                                        <span class="font-mono break-all text-slate-500">{{ $latestInvoice }}</span>
                                                    @endif
                                                </div>

                                                <div class="text-slate-600">
                                                    <span class="font-semibold text-slate-900">Type:</span>
                                                    subscription
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </dl>

                                {{-- actions --}}
                                <div class="mt-7 flex flex-col sm:flex-row gap-3 no-print">
                                    @if ($receiptUrl)
                                        <a
                                            href="{{ $receiptUrl }}"
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
                                    Monthly partners make the biggest difference over time — stability, trust, and a real path forward.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- next-step chips --}}
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

                    /* hide site chrome only */
                    header, nav, footer { display: none !important; }

                    /* IMPORTANT: allow previously "no-print" content to print (your pills + hero text) */
                    .no-print { display: block !important; }

                    body, main { background: #fff !important; }

                    .receipt-paper {
                        box-shadow: none !important;
                        border: 1px solid #CBD5E1 !important;
                        break-inside: avoid;
                        page-break-inside: avoid;
                        width: 100% !important;
                        max-width: none !important;
                    }

                    .receipt-body { padding: 18px !important; }

                    .print-only { display: inline-flex !important; }

                    /* ensure badges/gradients can print (user must enable Background graphics too) */
                    * {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
            <script>
                sessionStorage.removeItem('donation_widget_attempt_id');
            </script>
        @endonce
    </main>
</x-layouts.app>
