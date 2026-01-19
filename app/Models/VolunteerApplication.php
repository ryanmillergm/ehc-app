<?php

namespace App\Models;

use App\Models\ApplicationFormField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerApplication extends Model
{
    /** @use HasFactory<\Database\Factories\VolunteerApplicationFactory> */
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'user_id',
        'volunteer_need_id',
        'status',
        'answers',
        'interests',
        'availability',
        'reviewed_by',
        'reviewed_at',
        'internal_notes',
    ];

    protected $casts = [
        'answers'       => 'array',
        'interests'     => 'array',
        'availability'  => 'array',
        'reviewed_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(VolunteerNeed::class, 'volunteer_need_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Returns display-ready rows of: label + formatted answer.
     * Uses the NEED's attached form fields (ordered) as the "question list".
     *
     * @return array<int, array{key:string,label:string,type:string,value:mixed,display:string}>
     */
    public function presentedAnswers(): array
    {
        $need = $this->need?->loadMissing([
            'applicationForm.fields' => fn ($q) => $q->where('is_active', true)->orderBy('sort'),
        ]);

        $form = $need?->applicationForm;
        $fields = $form?->fields ?? collect();

        $answers = (array) ($this->answers ?? []);

        return $fields->map(function (ApplicationFormField $field) use ($answers) {
            $raw = data_get($answers, $field->key);

            $display = match ($field->type) {
                'checkbox_group' => $this->formatCheckboxGroup($field, $raw),
                'select', 'radio' => $this->formatOptionValue($field, $raw),
                'toggle' => $raw ? 'Yes' : 'No',
                default => is_array($raw) ? json_encode($raw) : (string) ($raw ?? ''),
            };

            return [
                'key'     => $field->key,
                'label'   => $field->label,
                'type'    => $field->type,
                'value'   => $raw,
                'display' => $display,
            ];
        })->values()->all();
    }

    protected function formatOptionValue(ApplicationFormField $field, mixed $raw): string
    {
        if (! is_string($raw) || $raw === '') {
            return '';
        }

        $options = $field->options();
        return $options[$raw] ?? $raw;
    }

    protected function formatCheckboxGroup(ApplicationFormField $field, mixed $raw): string
    {
        if (! is_array($raw)) {
            return '';
        }

        $options = $field->options();

        $labels = collect($raw)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn ($v) => $options[$v] ?? $v)
            ->values()
            ->all();

        return implode(', ', $labels);
    }

    /**
     * Availability is stored under answers.availability if enabled.
     * This returns a compact list like: "Mon AM, Wed PM"
     */
    public function availabilitySummary(): string
    {
        $availability = $this->availability;

        if (! is_array($availability)) {
            return '';
        }

        $dayLabels = [
            'sun' => 'Sun', 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed',
            'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat',
        ];

        $out = [];

        foreach ($availability as $day => $slots) {
            if (! isset($dayLabels[$day]) || ! is_array($slots)) {
                continue;
            }

            if (! empty($slots['am'])) {
                $out[] = "{$dayLabels[$day]} AM";
            }
            if (! empty($slots['pm'])) {
                $out[] = "{$dayLabels[$day]} PM";
            }
        }

        return implode(', ', $out);
    }
}
