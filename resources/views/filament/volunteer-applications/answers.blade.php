@php
    /** @var \App\Models\VolunteerApplication|null $record */
    $record = $getRecord();

    $need = $record?->need;
    $form = $need?->applicationForm;

    // Answers JSON (key => value)
    $answers = (array) ($record?->answers ?? []);

    // Availability is stored on volunteer_applications.availability 
    $availability = $record?->availability;

    // The system's real "question list" is fieldPlacements (not fields)
    // We will render ALL placements (active ones first), and show "—" if unanswered.
    $placements = $form?->fieldPlacements
        ? $form->fieldPlacements
            ->filter(fn ($p) => (bool) ($p->is_active ?? false))
            ->sortBy(fn ($p) => $p->sort ?? 0)
            ->values()
        : collect();

    // Build a set of "known keys" from placements so we can also show any extra answers
    // that exist in the JSON but are not represented by a placement.
    $placementKeys = $placements
        ->map(fn ($p) => $p->field?->key)
        ->filter()
        ->values()
        ->all();

    $extraAnswerKeys = collect(array_keys($answers))
        ->diff($placementKeys)
        ->values();
@endphp

<div class="space-y-6">
    @if (! $record)
        <div class="text-sm text-gray-500">No record loaded.</div>
    @elseif (! $need)
        <div class="text-sm text-gray-500">This application is not linked to a volunteer need.</div>
    @elseif (! $form)
        <div class="text-sm text-gray-500">This volunteer need is not linked to an application form.</div>
    @else
        {{-- 1) Placement-driven fields (never hidden; show — when blank) --}}
        @if ($placements->isEmpty())
            <div class="text-sm text-gray-500">
                This form currently has no active placed fields.
            </div>
        @endif

        @foreach ($placements as $placement)
            @php
                $field = $placement->field;

                $key = $field?->key ?? '';
                $type = $field?->type ?? '';
                $value = $key !== '' ? data_get($answers, $key) : null;

                // Label preference:
                // - placement label() if present
                // - else field label
                // - else key
                $label = method_exists($placement, 'label') ? ($placement->label() ?: null) : null;
                $label = $label ?: ($field->label ?? null);
                $label = $label ?: ($key ?: 'Field');

                // Help text preference:
                // - placement-level help (if you have it)
                // - else field help_text
                $helpText = method_exists($placement, 'helpText') ? ($placement->helpText() ?: null) : null;
                $helpText = $helpText ?: ($field->help_text ?? null);

                // Format display based on field type.
                // For select/radio/checkbox_group, try to resolve option labels via placement->options()
                $display = '';

                if ($type === 'checkbox_group') {
                    if (is_array($value)) {
                        $options = method_exists($placement, 'options') ? (array) $placement->options() : [];
                        $labels = collect($value)
                            ->filter(fn ($v) => is_string($v) && $v !== '')
                            ->map(fn ($v) => $options[$v] ?? $v)
                            ->values()
                            ->all();

                        $display = implode(', ', $labels);
                    } else {
                        $display = is_null($value) ? '' : (string) $value;
                    }
                } elseif ($type === 'select' || $type === 'radio') {
                    if (is_string($value) && $value !== '') {
                        $options = method_exists($placement, 'options') ? (array) $placement->options() : [];
                        $display = (string) ($options[$value] ?? $value);
                    } else {
                        $display = '';
                    }
                } elseif ($type === 'toggle') {
                    $display = $value ? 'Yes' : 'No';
                } else {
                    $display = is_array($value) ? json_encode($value) : (string) ($value ?? '');
                }
            @endphp

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">
                            {{ $label }}
                            @if (($field?->is_required ?? false) === true)
                                <span class="text-danger-600">*</span>
                            @endif
                        </div>

                        @if ($helpText)
                            <div class="mt-1 text-xs text-gray-500">{{ $helpText }}</div>
                        @endif
                    </div>

                    <div class="text-xs text-gray-400">
                        {{ $key }}
                    </div>
                </div>

                <div class="mt-3 text-sm text-gray-800 whitespace-pre-wrap">
                    {{ filled($display) ? $display : '—' }}
                </div>

                <div class="mt-2 text-xs text-gray-400">
                    type: {{ $type ?: '—' }}
                </div>
            </div>
        @endforeach

        {{-- 2) Any extra answers keys that exist in JSON but aren't mapped to a placement (never hidden) --}}
        @if ($extraAnswerKeys->isNotEmpty())
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="text-sm font-semibold text-gray-900">Other answers</div>
                <div class="mt-3 space-y-3">
                    @foreach ($extraAnswerKeys as $k)
                        @php
                            $v = data_get($answers, $k);

                            $d = match (true) {
                                is_bool($v) => $v ? 'Yes' : 'No',
                                is_array($v) => json_encode($v),
                                default => (string) ($v ?? ''),
                            };
                        @endphp

                        <div class="rounded-lg border border-gray-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="text-sm font-semibold text-gray-900">{{ $k }}</div>
                                <div class="text-xs text-gray-400">key</div>
                            </div>

                            <div class="mt-2 text-sm text-gray-800 whitespace-pre-wrap">
                                {{ filled($d) ? $d : '—' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 3) Availability matrix (from volunteer_applications.availability column) --}}
        @php
            $days = [
                'sun' => 'Sun',
                'mon' => 'Mon',
                'tue' => 'Tue',
                'wed' => 'Wed',
                'thu' => 'Thu',
                'fri' => 'Fri',
                'sat' => 'Sat',
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
        @else
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="text-sm font-semibold text-gray-900">Availability</div>
                <div class="mt-2 text-sm text-gray-500">—</div>
            </div>
        @endif
    @endif
</div>
