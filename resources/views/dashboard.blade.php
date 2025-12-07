{{-- resources/views/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">
                    Dashboard
                </h1>
                <p class="mt-1 text-xs text-slate-500">
                    Welcome back, {{ auth()->user()->first_name ?? auth()->user()->name }}.
                </p>
            </div>

            <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500">
                <span>Signed in as</span>
                <span class="font-semibold text-slate-900">
                    {{ auth()->user()->email }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-50">
        <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 space-y-6">

            {{-- TOP ROW: KEY METRICS --}}
            <section class="grid gap-4 md:grid-cols-3">
                {{-- Lifetime --}}
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-4">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Lifetime giving
                    </p>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">
                        {{ $lifetimeTotal ?? '—' }}
                    </p>
                    <p class="mt-1 text-[0.75rem] text-slate-500">
                        Total of all recorded gifts.
                    </p>
                </div>

                {{-- This year --}}
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-4">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Year to date ({{ now()->year }})
                    </p>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">
                        {{ $yearToDateTotal ?? '—' }}
                    </p>
                    <p class="mt-1 text-[0.75rem] text-slate-500">
                        All gifts made so far this year.
                    </p>
                </div>

                {{-- Recurring --}}
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-4">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Active recurring gifts
                    </p>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">
                        {{ $activeRecurringCount ?? '—' }}
                    </p>
                    <p class="mt-1 text-[0.75rem] text-slate-500">
                        Ongoing monthly / recurring donations.
                    </p>
                </div>
            </section>

            {{-- MAIN ROW: RECENT GIFTS + QUICK ACTIONS --}}
            <section class="grid gap-6 lg:grid-cols-12">
                {{-- Recent gifts table --}}
                <div class="lg:col-span-8">
                    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">
                                    Recent gifts
                                </h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Your latest donations at a glance.
                                </p>
                            </div>

                            <a href="{{ route('giving.index') }}"
                               class="text-xs font-semibold text-rose-700 hover:text-rose-800">
                                View all →
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50/80">
                                    <tr>
                                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                                            Date
                                        </th>
                                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                                            Amount
                                        </th>
                                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                                            Type
                                        </th>
                                        <th scope="col" class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @isset($recentGifts)
                                        @forelse ($recentGifts as $gift)
                                            <tr class="hover:bg-slate-50/80">
                                                <td class="px-5 py-3 whitespace-nowrap text-slate-700 text-sm">
                                                    {{ optional($gift->created_at)->format('M j, Y') ?? '—' }}
                                                </td>

                                                <td class="px-5 py-3 whitespace-nowrap font-semibold text-slate-900 text-sm">
                                                    @php
                                                        $amount = null;
                                                        if (isset($gift->amount_cents)) {
                                                            $amount = '$' . number_format($gift->amount_cents / 100, 2);
                                                        } elseif (isset($gift->amount)) {
                                                            $amount = $gift->amount;
                                                        }
                                                    @endphp
                                                    {{ $amount ?? '—' }}
                                                </td>

                                                <td class="px-5 py-3 whitespace-nowrap text-slate-600 text-sm">
                                                    {{ $gift->type_label ?? $gift->type ?? 'Gift' }}
                                                </td>

                                                <td class="px-5 py-3 whitespace-nowrap text-sm">
                                                    @php
                                                        $status = $gift->status ?? 'succeeded';
                                                        $statusColors = [
                                                            'succeeded' => 'bg-emerald-100 text-emerald-700',
                                                            'paid'      => 'bg-emerald-100 text-emerald-700',
                                                            'pending'   => 'bg-amber-100 text-amber-700',
                                                            'failed'    => 'bg-rose-100 text-rose-700',
                                                            'canceled'  => 'bg-slate-100 text-slate-700',
                                                        ];
                                                        $badgeClass = $statusColors[$status] ?? 'bg-slate-100 text-slate-700';
                                                    @endphp
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[0.7rem] font-semibold {{ $badgeClass }}">
                                                        {{ ucfirst($status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-5 py-4 text-sm text-slate-500">
                                                    No gifts recorded yet. Once you give, your recent donations will appear here.
                                                </td>
                                            </tr>
                                        @endforelse
                                    @else
                                        <tr>
                                            <td colspan="4" class="px-5 py-4 text-sm text-slate-500">
                                                Recent gifts data has not been wired up yet.
                                            </td>
                                        </tr>
                                    @endisset
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Quick actions / account panel --}}
                <div class="lg:col-span-4 space-y-4">
                    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-5">
                        <h2 class="text-sm font-semibold text-slate-900">
                            Quick actions
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Common things you might want to do.
                        </p>

                        <div class="mt-4 space-y-2 text-sm">
                            <a href="{{ route('giving.index') }}"
                               class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:border-rose-400 hover:bg-rose-50/60 transition">
                                <span class="text-slate-800">View giving history</span>
                                <span class="text-xs font-semibold text-rose-700">Open</span>
                            </a>

                            <a href="{{ route('profile.show') }}"
                               class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:border-rose-400 hover:bg-rose-50/60 transition">
                                <span class="text-slate-800">Update profile</span>
                                <span class="text-xs font-semibold text-rose-700">Edit</span>
                            </a>

                            <a href="{{ route('addresses.index') }}"
                               class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 hover:border-rose-400 hover:bg-rose-50/60 transition">
                                <span class="text-slate-800">Manage addresses</span>
                                <span class="text-xs font-semibold text-rose-700">Manage</span>
                            </a>
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-900 text-slate-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">
                            Giving notes
                        </p>
                        <p class="mt-2 text-sm text-slate-100 leading-relaxed">
                            Your recurring gifts can be adjusted or canceled at any time from the
                            “My Giving” page. If you ever need help, just reply to an email receipt.
                        </p>
                    </div>
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
