@php
    /** @var \App\Models\VolunteerApplication $record */
    $rows = $record->presentedAnswers();
    $availability = $record->availabilitySummary();
@endphp

<div class="space-y-4">
    <div class="grid gap-2 text-sm">
        <div><span class="font-semibold">Applicant:</span> {{ $record->user?->name }} ({{ $record->user?->email }})</div>
        <div><span class="font-semibold">Need:</span> {{ $record->need?->title }}</div>
        @if($availability !== '')
            <div><span class="font-semibold">Availability:</span> {{ $availability }}</div>
        @endif
    </div>

    <div class="divide-y divide-gray-200 rounded-xl border border-gray-200">
        @forelse($rows as $row)
            <div class="p-4">
                <div class="text-sm font-semibold text-gray-900">
                    {{ $row['label'] }}
                </div>
                <div class="mt-1 text-sm text-gray-700 whitespace-pre-line">
                    {{ $row['display'] !== '' ? $row['display'] : '—' }}
                </div>
                <div class="mt-1 text-xs text-gray-400">
                    key: {{ $row['key'] }} · type: {{ $row['type'] }}
                </div>
            </div>
        @empty
            <div class="p-4 text-sm text-gray-600">
                No fields found on the attached form.
            </div>
        @endforelse
    </div>
</div>
