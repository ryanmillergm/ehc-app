@php
    /** @var \App\Models\VolunteerApplication $record */
    $record = $this->record;
    $rows = $record->presentedAnswers();
    $availability = $record->availabilitySummary();
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Volunteer Application #{{ $record->id }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; color: #0f172a; }
        .wrap { max-width: 900px; margin: 24px auto; padding: 0 18px; }
        h1 { font-size: 22px; margin: 0; }
        .meta { margin-top: 10px; font-size: 13px; color: #334155; }
        .box { margin-top: 18px; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
        .row { padding: 14px 16px; border-top: 1px solid #e2e8f0; }
        .row:first-child { border-top: 0; }
        .label { font-weight: 700; font-size: 13px; color: #0f172a; }
        .value { margin-top: 6px; white-space: pre-line; font-size: 13px; color: #1f2937; }
        .muted { color: #64748b; font-size: 12px; }
        @media print {
            .no-print { display: none !important; }
            .wrap { margin: 0; max-width: none; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="no-print" style="display:flex; gap:10px; justify-content:flex-end;">
        <button onclick="window.print()" style="border:1px solid #e2e8f0; background:#fff; padding:8px 12px; border-radius:10px; cursor:pointer;">
            Print
        </button>
        <button onclick="window.close()" style="border:1px solid #e2e8f0; background:#fff; padding:8px 12px; border-radius:10px; cursor:pointer;">
            Close
        </button>
    </div>

    <h1>Volunteer Application #{{ $record->id }}</h1>

    <div class="meta">
        <div><strong>Applicant:</strong> {{ $record->user?->name }} ({{ $record->user?->email }})</div>
        <div><strong>Need:</strong> {{ $record->need?->title }}</div>
        <div><strong>Submitted:</strong> {{ $record->created_at?->toDayDateTimeString() }}</div>
        @if($availability !== '')
            <div><strong>Availability:</strong> {{ $availability }}</div>
        @endif
    </div>

    <div class="box">
        @foreach($rows as $row)
            <div class="row">
                <div class="label">{{ $row['label'] }}</div>
                <div class="value">{{ $row['display'] !== '' ? $row['display'] : '—' }}</div>
                <div class="muted">key: {{ $row['key'] }} · type: {{ $row['type'] }}</div>
            </div>
        @endforeach
    </div>
</div>

<script>
    window.print();
</script>
</body>
</html>
