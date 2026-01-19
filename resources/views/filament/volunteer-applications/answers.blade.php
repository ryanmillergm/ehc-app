@php
    /** @var \App\Models\VolunteerApplication|null $record */
    $record = $getRecord();

    $need = $record?->need;
    $form = $need?->applicationForm;

    $fields = $form?->fields
        ? $form->fields->where('is_active', true)->sortBy('sort')->values()
        : collect();

    $answers = (array) ($record?->answers ?? []);
@endphp

<div class="space-y-6">
    @if (! $record)
        <div class="text-sm text-gray-500">No record loaded.</div>
    @elseif (! $need)
        <div class="text-sm text-gray-500">This application is not linked to a volunteer need.</div>
    @elseif (! $form)
        <div class="text-sm text-gray-500">This volunteer need is not linked to an application form.</div>
    @else
        @if ($fields->isEmpty())
            <div class="text-sm text-gray-500">This form has no active fields.</div>
        @endif

        {{-- Builder-driven fields --}}
        @foreach ($fields as $field)
            @php
                $value = data_get($answers, $field->key);

                // Normalize display a bit by type
                $display = match ($field->type) {
                    'checkbox_group' => is_array($value) ? implode(', ', $value) : (string) $value,
                    'toggle' => is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value,
                    default => is_array($value) ? json_encode($value) : (string) $value,
                };
            @endphp

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">
                            {{ $field->label }}
                            @if ($field->is_required)
                                <span class="text-danger-600">*</span>
                            @endif
                        </div>

                        @if ($field->help_text)
                            <div class="mt-1 text-xs text-gray-500">{{ $field->help_text }}</div>
                        @endif
                    </div>

                    <div class="text-xs text-gray-400">
                        {{ $field->key }}
                    </div>
                </div>

                <div class="mt-3 text-sm text-gray-800 whitespace-pre-wrap">
                    {{ filled($display) ? $display : '—' }}
                </div>
            </div>
        @endforeach

        {{-- Built-in availability block (saved inside answers.availability by Apply component) --}}
        @php
            $availability = data_get($answers, 'availability');
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

        @if (is_array($availability))
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="text-sm font-semibold text-gray-900">Availability</div>
                <div class="mt-3 overflow-hidden rounded-lg border border-gray-200">
                    <div class="grid grid-cols-3 bg-gray-50 text-xs font-semibold text-gray-700">
                        <div class="px-3 py-2">Day</div>
                        <div class="px-3 py-2 text-center">AM</div>
                        <div class="px-3 py-2 text-center">PM</div>
                    </div>

                    @foreach ($days as $key => $label)
                        @php
                            $am = (bool) data_get($availability, "{$key}.am", false);
                            $pm = (bool) data_get($availability, "{$key}.pm", false);
                        @endphp

                        <div class="grid grid-cols-3 border-t border-gray-200 text-sm">
                            <div class="px-3 py-2 font-medium text-gray-800">{{ $label }}</div>
                            <div class="px-3 py-2 text-center">{{ $am ? '✓' : '—' }}</div>
                            <div class="px-3 py-2 text-center">{{ $pm ? '✓' : '—' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
