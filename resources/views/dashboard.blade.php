{{-- resources/views/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="bg-white shadow-xl sm:rounded-2xl min-h-[60vh] flex items-center"
            >
                <div class="w-full py-10 px-6 sm:px-10">
                    <div class="flex flex-col gap-8">

                        {{-- Heading + intro copy --}}
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-indigo-600 uppercase">
                                Welcome back
                            </p>

                            <h3 class="mt-2 text-2xl sm:text-3xl font-semibold text-gray-900">
                                {{ auth()->user()->first_name ?? auth()->user()->name }}!
                            </h3>

                            <p class="mt-3 text-sm text-gray-600 max-w-xl">
                                From here you can manage your profile, update your address,
                                and review your donations.
                            </p>
                        </div>

                        <div class="border-t border-gray-100"></div>

                        {{-- Actions: stacked buttons --}}
                        <div class="grid gap-3 sm:max-w-xs">
                            <a
                                href="{{ route('giving.index') }}"
                                class="inline-flex justify-center items-center px-4 py-2.5
                                       rounded-lg text-sm font-semibold text-white
                                       bg-indigo-600 hover:bg-indigo-700
                                       shadow-sm focus:outline-none
                                       focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                View My Giving
                            </a>

                            <a
                                href="{{ route('profile.show') }}"
                                class="inline-flex justify-center items-center px-4 py-2.5
                                       rounded-lg text-sm font-semibold text-gray-700
                                       bg-white border border-gray-300
                                       hover:bg-gray-50
                                       focus:outline-none
                                       focus:ring-2 focus:ring-offset-2 focus:ring-gray-300"
                            >
                                Edit Profile
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
