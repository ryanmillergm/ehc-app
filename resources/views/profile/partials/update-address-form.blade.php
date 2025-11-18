<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Mailing Address') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Update the address we use for receipts and communication.') }}
        </p>
    </header>

    @php
        $address = auth()->user()->primaryAddress;
    @endphp

    <form method="POST" action="{{ route('profile.address.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        {{-- Address line 1 --}}
        <div class="col-span-6 sm:col-span-4">
            <x-label for="line1" value="{{ __('Address line 1') }}" />
            <x-input
                id="line1"
                name="line1"
                type="text"
                class="mt-1 block w-full"
                autocomplete="address-line1"
                :value="old('line1', $address->line1 ?? '')"
                required
            />
            <x-input-error for="line1" class="mt-2" />
        </div>

        {{-- Address line 2 --}}
        <div class="col-span-6 sm:col-span-4">
            <x-label for="line2" value="{{ __('Address line 2') }}" />
            <x-input
                id="line2"
                name="line2"
                type="text"
                class="mt-1 block w-full"
                autocomplete="address-line2"
                :value="old('line2', $address->line2 ?? '')"
            />
            <x-input-error for="line2" class="mt-2" />
        </div>

        {{-- City / State / Postal --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <x-label for="city" value="{{ __('City') }}" />
                <x-input
                    id="city"
                    name="city"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="address-level2"
                    :value="old('city', $address->city ?? '')"
                />
                <x-input-error for="city" class="mt-2" />
            </div>

            <div>
                <x-label for="state" value="{{ __('State / Province') }}" />
                <x-input
                    id="state"
                    name="state"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="address-level1"
                    :value="old('state', $address->state ?? '')"
                />
                <x-input-error for="state" class="mt-2" />
            </div>

            <div>
                <x-label for="postal_code" value="{{ __('Postal / ZIP') }}" />
                <x-input
                    id="postal_code"
                    name="postal_code"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="postal-code"
                    :value="old('postal_code', $address->postal_code ?? '')"
                />
                <x-input-error for="postal_code" class="mt-2" />
            </div>
        </div>

        {{-- Country + phone --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-label for="country" value="{{ __('Country code (e.g. US)') }}" />
                <x-input
                    id="country"
                    name="country"
                    type="text"
                    maxlength="2"
                    class="mt-1 block w-full uppercase"
                    autocomplete="country"
                    :value="old('country', $address->country ?? 'US')"
                />
                <x-input-error for="country" class="mt-2" />
            </div>

            <div>
                <x-label for="phone" value="{{ __('Phone') }}" />
                <x-input
                    id="phone"
                    name="phone"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="tel"
                    :value="old('phone', $address->phone ?? '')"
                />
                <x-input-error for="phone" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-button>
                {{ __('Save') }}
            </x-button>

            @if (session('status') === 'primary-address-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>
</section>
