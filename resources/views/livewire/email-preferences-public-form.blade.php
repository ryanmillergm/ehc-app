<div>
    <div class="text-sm text-slate-600">
        Managing preferences for <span class="font-semibold text-slate-900">{{ $email }}</span>
    </div>

    <form wire:submit.prevent="save" class="mt-6">
        <div class="space-y-6">
            <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                <div class="font-semibold text-gray-900">Heads up</div>
                <div class="mt-1">
                    Transactional emails (receipts, confirmations, etc.) are required and can’t be unsubscribed from here.
                </div>
            </div>

            {{-- Global marketing opt-out --}}
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model.live="optOutAllMarketing"
                       class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>
                    <span class="block font-medium text-gray-900">Unsubscribe from all marketing emails</span>
                    <span class="block text-sm text-gray-600">
                        Stops newsletters, outreach updates, events, and other marketing messages.
                    </span>
                </span>
            </label>

            {{-- Marketing lists --}}
            <div class="{{ $optOutAllMarketing ? 'opacity-50 pointer-events-none' : '' }}">
                <div class="text-sm font-semibold text-gray-900">Marketing</div>

                <div class="mt-3 space-y-4">
                    @forelse ($marketingLists as $list)
                        <label class="flex items-start gap-3">
                            <input
                                type="checkbox"
                                wire:model.live="subscriptions.{{ $list['id'] }}"
                                @disabled(! $list['is_opt_outable'])
                                class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-60"
                            >
                            <span class="flex-1">
                                <span class="block font-medium text-gray-900">
                                    {{ $list['label'] }}
                                    @if (! $list['is_opt_outable'])
                                        <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                            Locked
                                        </span>
                                    @endif
                                </span>

                                @if (! empty($list['description']))
                                    <span class="block text-sm text-gray-600">{{ $list['description'] }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <div class="text-sm text-gray-600">
                            No marketing lists are configured yet.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Transactional lists (read-only) --}}
            <div>
                <div class="text-sm font-semibold text-gray-900">Transactional</div>

                <div class="mt-3 space-y-3">
                    @forelse ($transactionalLists as $list)
                        <div class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                            <div class="mt-0.5 text-gray-500">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 8V6a5 5 0 1110 0v2h.5A1.5 1.5 0 0117 9.5v7A1.5 1.5 0 0115.5 18h-11A1.5 1.5 0 013 16.5v-7A1.5 1.5 0 014.5 8H5zm2-2v2h6V6a3 3 0 10-6 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>

                            <div class="flex-1">
                                <div class="font-medium text-gray-900">{{ $list['label'] }}</div>
                                @if (! empty($list['description']))
                                    <div class="text-sm text-gray-600">{{ $list['description'] }}</div>
                                @endif
                            </div>

                            <div class="text-xs font-semibold text-gray-600">
                                Required
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-600">
                            No transactional lists are configured yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <x-button>Save</x-button>

            <x-action-message class="ml-2" on="saved">
                Saved.
            </x-action-message>
        </div>
    </form>
</div>
