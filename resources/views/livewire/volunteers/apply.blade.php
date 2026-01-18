<div class="bg-white text-slate-900">
    <div class="mx-auto max-w-3xl px-6 sm:px-8 lg:px-12 pt-10 pb-16">
        <div class="flex flex-col gap-2">
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                Volunteer Application
            </h1>

            <div class="inline-flex items-center gap-2 rounded-full bg-slate-900 text-white px-4 py-1.5 text-xs font-semibold w-fit">
                Applying for:
                <span class="text-rose-300">{{ $need->title }}</span>
            </div>

            @if ($need->description)
                <p class="mt-2 text-slate-600">
                    {{ $need->description }}
                </p>
            @endif
        </div>

        <div class="mt-8 rounded-3xl border border-slate-200 bg-white shadow-sm p-6 sm:p-8 space-y-6">
            <div>
                <label class="block text-sm font-semibold text-slate-900">Why do you want to volunteer?</label>

                <textarea
                    wire:model.defer="message"
                    rows="5"
                    class="mt-2 w-full rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                    placeholder="Share a bit about your heart to serve..."
                ></textarea>

                @error('message')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
                @error('duplicate')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="text-sm font-semibold text-slate-900">Areas of interest</div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-slate-700">
                    @foreach ([
                        'food' => 'Food service',
                        'cleanup' => 'Setup / cleanup',
                        'prayer' => 'Prayer + conversation',
                        'logistics' => 'Logistics',
                        'followup' => 'Discipleship follow-up',
                        'admin' => 'Admin help',
                    ] as $key => $label)
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                            <input type="checkbox" wire:model.defer="interests" value="{{ $key }}"
                                class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="text-sm font-semibold text-slate-900">Availability</div>

                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    @foreach ([
                        'thursday' => 'Thursday',
                        'sunday' => 'Sunday',
                        'weekday' => 'Weekdays',
                        'flexible' => 'Flexible',
                    ] as $key => $label)
                        <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2">
                            <input type="checkbox" wire:model.defer="availability" value="{{ $key }}"
                                class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="pt-2">
                <button
                    wire:click="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-full bg-rose-700 px-7 py-3.5
                           text-sm font-semibold text-white shadow-sm hover:bg-rose-800 disabled:opacity-60"
                >
                    Submit application
                </button>
            </div>
        </div>
    </div>
</div>
