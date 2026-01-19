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

            @if ($submitted)
                @php
                    $content = (string) ($form->thank_you_content ?? '');
                    $format = (string) ($form->thank_you_format ?? 'text');
                    $rendersHtml = in_array($format, ['html', 'wysiwyg'], true);
                @endphp

                <div class="space-y-4">
                    <h2 class="text-2xl font-extrabold tracking-tight">Thank you!</h2>

                    @if ($rendersHtml)
                        <div class="thank-you-content prose max-w-none">{!! $content !!}</div>
                    @else
                        <p class="text-slate-700 whitespace-pre-line">{{ $content }}</p>
                    @endif
                </div>
            @else

                {{-- Dynamic builder-driven fields --}}
                @foreach ($fields as $field)
                    <div>
                        <label class="block text-sm font-semibold text-slate-900">
                            {{ $field->label }}
                            @if($field->is_required) <span class="text-rose-600">*</span> @endif
                        </label>

                        @if ($field->help_text)
                            <p class="mt-1 text-sm text-slate-500">{{ $field->help_text }}</p>
                        @endif

                        @php
                            $wireKey = "answers.{$field->key}";
                            $options = $field->options();
                            $placeholder = data_get($field->config, 'placeholder');
                            $rows = data_get($field->config, 'rows', 4);
                        @endphp

                        @switch($field->type)

                            @case('text')
                                <input
                                    type="text"
                                    wire:model.defer="{{ $wireKey }}"
                                    placeholder="{{ $placeholder }}"
                                    class="mt-2 w-full rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                                />
                                @break

                            @case('textarea')
                                <textarea
                                    wire:model.defer="{{ $wireKey }}"
                                    rows="{{ $rows }}"
                                    placeholder="{{ $placeholder }}"
                                    class="mt-2 w-full rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                                ></textarea>
                                @break

                            @case('select')
                                <select
                                    wire:model.defer="{{ $wireKey }}"
                                    class="mt-2 w-full rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                                >
                                    <option value="">Select...</option>
                                    @foreach ($options as $k => $label)
                                        <option value="{{ $k }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @break

                            @case('radio')
                                <div class="mt-3 space-y-2 text-sm text-slate-700">
                                    @foreach ($options as $k => $label)
                                        <label class="flex items-center gap-2">
                                            <input type="radio"
                                                   wire:model.defer="{{ $wireKey }}"
                                                   value="{{ $k }}"
                                                   class="text-rose-600 focus:ring-rose-500" />
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @break

                            @case('checkbox_group')
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-slate-700">
                                    @foreach ($options as $k => $label)
                                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2">
                                            <input type="checkbox"
                                                   wire:model.defer="{{ $wireKey }}"
                                                   value="{{ $k }}"
                                                   class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @break

                            @case('toggle')
                                <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox"
                                           wire:model.defer="{{ $wireKey }}"
                                           class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                    <span>Yes</span>
                                </label>
                                @break

                        @endswitch

                        @error("answers.{$field->key}")
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                {{-- Optional availability block (per-form toggle) --}}
                @if ($form->use_availability)
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Availability</div>
                        <p class="mt-1 text-sm text-slate-500">Select the days/times you’re generally available.</p>

                        @php
                            $days = [
                                'mon' => 'Mon',
                                'tue' => 'Tue',
                                'wed' => 'Wed',
                                'thu' => 'Thu',
                                'fri' => 'Fri',
                                'sat' => 'Sat',
                                'sun' => 'Sun',
                            ];
                        @endphp

                        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200">
                            <div class="grid grid-cols-3 bg-slate-50 text-xs font-semibold text-slate-700">
                                <div class="px-4 py-3">Day</div>
                                <div class="px-4 py-3 text-center">AM</div>
                                <div class="px-4 py-3 text-center">PM</div>
                            </div>

                            @foreach ($days as $key => $label)
                                <div class="grid grid-cols-3 items-center border-t border-slate-200 text-sm">
                                    <div class="px-4 py-3 font-medium text-slate-800">{{ $label }}</div>

                                    <div class="px-4 py-3 text-center">
                                        <input type="checkbox"
                                               wire:model.defer="availability.{{ $key }}.am"
                                               class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                    </div>

                                    <div class="px-4 py-3 text-center">
                                        <input type="checkbox"
                                               wire:model.defer="availability.{{ $key }}.pm"
                                               class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @error('availability.*.*')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                @error('duplicate')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror

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

            @endif
        </div>
    </div>
</div>

<script>
    function setLazyLoadingOnThankYouImages() {
        document.querySelectorAll('.thank-you-content img').forEach(img => {
            // Don't overwrite if already set
            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }
        });
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', () => {
        setLazyLoadingOnThankYouImages();
    });

    // Livewire updates (v2/v3 compatible)
    document.addEventListener('livewire:load', () => {
        setLazyLoadingOnThankYouImages();

        // Re-run after any DOM patch
        if (window.Livewire?.hook) {
            Livewire.hook('message.processed', () => {
                setLazyLoadingOnThankYouImages();
            });
        }
    });
</script>
