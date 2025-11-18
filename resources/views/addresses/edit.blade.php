<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit address') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">

                    <h3 class="text-lg font-medium text-gray-900">
                        {{ $address->label ?: __('Address') }}
                    </h3>

                    <form method="POST"
                          action="{{ route('addresses.update', $address) }}"
                          class="mt-6 space-y-6"
                          x-data="{ country: '{{ old('country', $address->country ?? 'US') }}' }">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="label" value="{{ __('Label (home, office, etc.)') }}" />
                                <x-input id="label" name="label" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('label', $address->label) }}" />
                                <x-input-error for="label" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="company" value="{{ __('Company (optional)') }}" />
                                <x-input id="company" name="company" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('company', $address->company) }}" />
                                <x-input-error for="company" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="first_name" value="{{ __('First name') }}" />
                                <x-input id="first_name" name="first_name" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('first_name', $address->first_name) }}" />
                                <x-input-error for="first_name" class="mt-2" />
                            </div>
                            <div>
                                <x-label for="last_name" value="{{ __('Last name') }}" />
                                <x-input id="last_name" name="last_name" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('last_name', $address->last_name) }}" />
                                <x-input-error for="last_name" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-label for="line1" value="{{ __('Address line 1') }}" />
                            <x-input id="line1" name="line1" type="text"
                                     class="mt-1 block w-full"
                                     value="{{ old('line1', $address->line1) }}" />
                            <x-input-error for="line1" class="mt-2" />
                        </div>

                        <div>
                            <x-label for="line2" value="{{ __('Address line 2') }}" />
                            <x-input id="line2" name="line2" type="text"
                                     class="mt-1 block w-full"
                                     value="{{ old('line2', $address->line2) }}" />
                            <x-input-error for="line2" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-label for="city" value="{{ __('City') }}" />
                                <x-input id="city" name="city" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('city', $address->city) }}" />
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
                                            @selected(old('state', $address->state) === $code)>
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
                                            @selected(old('state', $address->state) === $code)>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Fallback text input --}}
                                <x-input id="state_other"
                                         type="text"
                                         name="state"
                                         x-show="country !== 'US' && country !== 'CA'"
                                         x-bind:disabled="country === 'US' || country === 'CA'"
                                         class="mt-1 block w-full"
                                         value="{{ old('state', $address->state) }}"
                                         autocomplete="address-level1" />

                                <x-input-error for="state" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="postal_code" value="{{ __('Postal / ZIP') }}" />
                                <x-input id="postal_code" name="postal_code" type="text"
                                         class="mt-1 block w-full"
                                         value="{{ old('postal_code', $address->postal_code) }}" />
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
                                            @selected(old('country', $address->country) === $code)>
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
                                         value="{{ old('phone', $address->phone) }}" />
                                <x-input-error for="phone" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_primary" value="1"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                       @checked(old('is_primary', $address->is_primary))>
                                <span class="ms-2 text-sm text-gray-600">
                                    {{ __('Set as primary address') }}
                                </span>
                            </label>

                            <div class="flex items-center gap-3">
                                <x-secondary-button type="button"
                                    onclick="window.history.back()">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-button>
                                    {{ __('Save changes') }}
                                </x-button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
