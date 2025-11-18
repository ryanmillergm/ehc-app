{{-- resources/views/giving/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Giving') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Status / flash --}}
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md text-sm">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Subscriptions --}}
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ __('Monthly Donations') }}
                    </h3>
                </div>

                <div class="px-6 py-4">
                    @if ($pledges->isEmpty())
                        <p class="text-sm text-gray-500">
                            You don’t have any monthly donations yet.
                        </p>
                    @else
                        <div class="space-y-4">
                            @foreach ($pledges as $pledge)
                                <div class="border border-gray-200 rounded-lg px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <div class="text-sm text-gray-900 font-semibold">
                                            ${{ number_format($pledge->amount_cents / 100, 2) }} / {{ $pledge->interval }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Status:
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                                @class([
                                                    'bg-green-100 text-green-800' => $pledge->status === 'active',
                                                    'bg-yellow-100 text-yellow-800' => in_array($pledge->status, ['incomplete', 'past_due']),
                                                    'bg-red-100 text-red-800' => $pledge->status === 'canceled',
                                                ])">
                                                {{ ucfirst($pledge->status) }}
                                            </span>
                                            @if ($pledge->cancel_at_period_end)
                                                <span class="ml-2 text-xs text-red-500">
                                                    (Will cancel at period end)
                                                </span>
                                            @endif
                                        </div>
                                        @if ($pledge->next_pledge_at)
                                            <div class="text-xs text-gray-500 mt-1">
                                                Next charge: {{ optional($pledge->next_pledge_at)->format('M j, Y') }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                        {{-- Change amount --}}
                                        @if ($pledge->status === 'active')
                                            <form method="POST" action="{{ route('giving.subscriptions.amount', $pledge) }}" class="flex items-center gap-2">
                                                @csrf
                                                <label class="text-xs text-gray-500">
                                                    New amount
                                                </label>
                                                <input
                                                    type="number"
                                                    name="amount_dollars"
                                                    step="1"
                                                    min="1"
                                                    value="{{ number_format($pledge->amount_cents / 100, 2, '.', '') }}"
                                                    class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                >
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-semibold rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                                >
                                                    Update
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Cancel at period end --}}
                                        @if ($pledge->status === 'active' && ! $pledge->cancel_at_period_end)
                                            <form
                                                method="POST"
                                                action="{{ route('giving.subscriptions.cancel', $pledge) }}"
                                                onsubmit="return confirm('Are you sure you want to cancel this monthly donation at the end of the current period?');"
                                            >
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300"
                                                >
                                                    Cancel at period end
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Donation history --}}
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ __('Donation History') }}
                    </h3>
                </div>

                <div class="px-6 py-4">
                    @if ($transactions->isEmpty())
                        <p class="text-sm text-gray-500">
                            You don’t have any donations recorded yet.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Receipt
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($transactions as $tx)
                                        <tr>
                                            <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                                {{ optional($tx->paid_at)->format('M j, Y') ?? '—' }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-gray-700">
                                                {{ $tx->pledge_id ? 'Monthly' : 'One-time' }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-900 font-medium">
                                                ${{ number_format($tx->amount_cents / 100, 2) }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                                    @class([
                                                        'bg-green-100 text-green-800' => $tx->status === 'succeeded',
                                                        'bg-yellow-100 text-yellow-800' => $tx->status === 'pending',
                                                        'bg-red-100 text-red-800' => $tx->status === 'failed',
                                                    ])">
                                                    {{ ucfirst($tx->status) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-right">
                                                @if ($tx->receipt_url)
                                                    <a
                                                        href="{{ $tx->receipt_url }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="text-xs text-indigo-600 hover:text-indigo-800 underline"
                                                    >
                                                        View
                                                    </a>
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $transactions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
