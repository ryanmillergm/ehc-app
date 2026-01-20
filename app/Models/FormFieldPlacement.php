<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FormFieldPlacement extends Model
{
    /** @use HasFactory<\Database\Factories\FormFieldPlacementFactory> */
    use HasFactory;

    protected $fillable = [
        'fieldable_type',
        'fieldable_id',
        'form_field_id',
        'is_required',
        'is_active',
        'sort',
        'label_override',
        'help_text_override',
        'config_override',
    ];

    protected $casts = [
        'is_required'    => 'boolean',
        'is_active'      => 'boolean',
        'sort'           => 'integer',
        'config_override'=> 'array',
    ];

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    public function label(): string
    {
        return $this->label_override ?: ($this->field?->label ?? '');
    }

    public function helpText(): ?string
    {
        return $this->help_text_override ?: ($this->field?->help_text ?? null);
    }

    /**
     * Merges base field config + placement override config.
     */
    public function config(): array
    {
        $base = $this->field?->config ?? [];
        $override = $this->config_override ?? [];

        if (! is_array($base)) {
            $base = [];
        }
        if (! is_array($override)) {
            $override = [];
        }

        return array_merge($base, $override);
    }

    /**
     * Return options for select/radio/checkbox_group.
     *
     * Supports BOTH:
     *   1) Preferred: config['options'] = [key => label]
     *   2) Back-compat: config = [key => label] (flat key/value pairs)
     */
    public function options(): array
    {
        $field = $this->field;

        if (! $field) {
            return [];
        }

        $type = (string) $field->type;

        // Only these field types use "options"
        if (! in_array($type, ['select', 'radio', 'checkbox_group'], true)) {
            return [];
        }

        $config = $this->config();

        if (! is_array($config) || $config === []) {
            return [];
        }

        // Preferred format: config['options'] = [...]
        $options = data_get($config, 'options');

        if (is_array($options) && $options !== []) {
            return $this->normalizeOptions($options);
        }

        // Back-compat format: config = [...]
        // But ignore known non-option keys in case config mixes things.
        $reservedKeys = [
            'options',
            'placeholder',
            'rows',
            'min',
            'max',
            'help',
            'label',
            'default',
        ];

        $maybeOptions = $config;
        foreach ($reservedKeys as $reserved) {
            unset($maybeOptions[$reserved]);
        }

        if (! is_array($maybeOptions) || $maybeOptions === []) {
            return [];
        }

        return $this->normalizeOptions($maybeOptions);
    }

    protected function normalizeOptions(array $options): array
    {
        $out = [];

        foreach ($options as $k => $label) {
            $k = is_string($k) ? trim($k) : (string) $k;
            $label = is_string($label) ? trim($label) : (string) $label;

            if ($k === '' || $label === '') {
                continue;
            }

            $out[$k] = $label;
        }

        return $out;
    }
}
