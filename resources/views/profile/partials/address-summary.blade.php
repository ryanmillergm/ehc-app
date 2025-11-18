@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $address = $user?->getPrimaryAddressOrFirstAttribute();
@endphp

<x-action-section>
    <x-slot name="title">
        {{ __('Mailing Address') }}
    </x-slot>

    <x-slot name="description">
        {{ __('The address we’ll use for receipts and communication.') }}
    </x-slot>

    <x-slot name="content">
        @if ($address)
            <div class="text-sm text-gray-700">
                <div class="font-semibold">
                    {{ $address->label ?: __('Primary address') }}
                    @if ($address->is_primary)
                        <span class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                            {{ __('Primary') }}
                        </span>
                    @endif
                </div>

                <div class="mt-1 text-gray-600">
                    {{ $address->first_name }} {{ $address->last_name }}<br>
                    {{ $address->line1 }} @if($address->line2)<br>{{ $address->line2 }}@endif<br>
                    {{ $address->city }}{{ $address->state ? ', '.$address->state : '' }} {{ $address->postal_code }}<br>
                    {{ $address->country }}
                    @if ($address->phone)
                        <br>{{ $address->phone }}
                    @endif
                </div>
            </div>
        @else
            <p class="text-sm text-gray-600">
                {{ __('You don’t have any addresses saved yet.') }}
            </p>
        @endif

        <div class="mt-5">
            <x-secondary-button type="button" onclick="window.location='{{ route('addresses.index') }}'">
                {{ __('Manage addresses') }}
            </x-secondary-button>
        </div>
    </x-slot>
</x-action-section>
