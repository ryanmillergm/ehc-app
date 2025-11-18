<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Addresses') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash messages --}}
            @if (session('status') === 'address-created')
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ __('Address added.') }}
                </div>
            @elseif (session('status') === 'address-updated')
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ __('Address updated.') }}
                </div>
            @elseif (session('status') === 'address-deleted')
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ __('Address deleted.') }}
                </div>
            @elseif (session('status') === 'address-primary-updated')
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ __('Primary address updated.') }}
                </div>
            @endif

            {{-- Add new address --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ __('Add a new address') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('You can save multiple addresses and choose which one is primary for receipts.') }}
                    </p>

                    <form method="POST"
                          action="{{ route('addresses.store') }}"
                          class="mt-6 space-y-6"
                          x-data="{ country: '{{ old('country', $defaultCountry) }}' }">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="label" value="{{ __('Label (home, office, etc.)') }}" />
                                <x-input id="label" name="label" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('label') }}" />
                                <x-input-error for="label" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="company" value="{{ __('Company (optional)') }}" />
                                <x-input id="company" name="company" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('company') }}" />
                                <x-input-error for="company" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="first_name" value="{{ __('First name') }}" />
                                <x-input id="first_name" name="first_name" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('first_name', $user->first_name) }}" />
                                <x-input-error for="first_name" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="last_name" value="{{ __('Last name') }}" />
                                <x-input id="last_name" name="last_name" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('last_name', $user->last_name) }}" />
                                <x-input-error for="last_name" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-label for="line1" value="{{ __('Address line 1') }}" />
                            <x-input id="line1" name="line1" type="text"
                                     class="mt-1 block w-full"
                                     value="{{ old('line1') }}" />
                            <x-input-error for="line1" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="line2" value="{{ __('Address line 2') }}" />
                            <x-input id="line2" name="line2" type="text"
                                     class="mt-1 block w-full"
                                     value="{{ old('line2') }}" />
                            <x-input-error for="line2" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-label for="city" value="{{ __('City') }}" />
                                <x-input id="city" name="city" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('city') }}" />
                                <x-input-error for="city" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="state" value="{{ __('State / Province') }}" />

                                {{-- US states --}}
                                <select id="state_us"
                                        name="state"
                                        x-show="country === 'US'"
                                        x-bind:disabled="country !== 'US'"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Select state') }}</option>
                                    @foreach ($usStates as $code => $name)
                                        <option value="{{ $code }}"
                                            @selected(old('state') === $code)>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Canadian provinces / territories --}}
                                <select id="state_ca"
                                        name="state"
                                        x-show="country === 'CA'"
                                        x-bind:disabled="country !== 'CA'"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Select province / territory') }}</option>
                                    @foreach ($caStates as $code => $name)
                                        <option value="{{ $code }}"
                                            @selected(old('state') === $code)>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Fallback text input for other countries --}}
                                <x-input id="state_other"
                                         type="text"
                                         name="state"
                                         x-show="country !== 'US' && country !== 'CA'"
                                         x-bind:disabled="country === 'US' || country === 'CA'"
                                         class="mt-1 block w-full"
                                         value="{{ old('state') }}"
                                         autocomplete="address-level1" />

                                <x-input-error for="state" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="postal_code" value="{{ __('Postal / ZIP') }}" />
                                <x-input id="postal_code" name="postal_code" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('postal_code') }}" />
                                <x-input-error for="postal_code" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="country" value="{{ __('Country') }}" />
                                <select id="country"
                                        name="country"
                                        x-model="country"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($countries as $code => $name)
                                        <option value="{{ $code }}"
                                            @selected(old('country', $defaultCountry) === $code)>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error for="country" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="phone" value="{{ __('Phone (optional)') }}" />
                                <x-input id="phone" name="phone" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('phone') }}" />
                                <x-input-error for="phone" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_primary" value="1"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                       @checked(old('is_primary'))>
                                <span class="ms-2 text-sm text-gray-600">
                                    {{ __('Set as primary address') }}
                                </span>
                            </label>

                            <div class="flex items-center gap-3">
                                <x-button>
                                    {{ __('Save address') }}
                                </x-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Saved addresses list --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ __('Your saved addresses') }}
                    </h3>

                    @if ($addresses->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">
                            {{ __('You have not added any addresses yet.') }}
                        </p>
                    @else
                        <div class="mt-4 divide-y divide-gray-100">
                            @foreach ($addresses as $address)
                                <div class="py-4 flex items-start justify-between">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $address->label ?: __('Address') }}
                                            </p>
                                            @if ($address->is_primary)
                                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                                    {{ __('Primary') }}
                                                </span>
                                            @endif
                                        </div>

                                        <p class="text-sm text-gray-600">
                                            {{ $address->first_name }} {{ $address->last_name }}
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            {{ $address->line1 }} @if($address->line2) , {{ $address->line2 }} @endif
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            {{ $address->city }},
                                            @if($address->state) {{ $address->state }}, @endif
                                            {{ $address->postal_code }}
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            {{ config('geo.countries.' . $address->country, $address->country) }}
                                        </p>
                                        @if ($address->phone)
                                            <p class="text-sm text-gray-600">
                                                {{ $address->phone }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex flex-col items-end gap-2 ms-4">
                                        <a href="{{ route('addresses.edit', $address) }}"
                                           class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                            {{ __('Edit') }}
                                        </a>

                                        @if (! $address->is_primary)
                                            <form method="POST" action="{{ route('addresses.make-primary', $address) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center rounded-md border border-indigo-500 bg-white px-3 py-1.5 text-xs font-medium text-indigo-600 shadow-sm hover:bg-indigo-50">
                                                    {{ __('Make primary') }}
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('addresses.destroy', $address) }}"
                                              onsubmit="return confirm('Delete this address?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center rounded-md border border-transparent bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 shadow-sm hover:bg-red-100">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
