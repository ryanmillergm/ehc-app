<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApplicationForm extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFormFactory> */
    use HasFactory;

    public const THANK_YOU_TEXT   = 'text';
    public const THANK_YOU_WYSIWYG = 'wysiwyg';
    public const THANK_YOU_HTML   = 'html';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'use_availability',
        'thank_you_format',
        'thank_you_content',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'use_availability' => 'boolean',
    ];

    /**
     * Polymorphic: a form has many placements (order controlled by sort).
     */
    public function fieldPlacements(): MorphMany
    {
        return $this->morphMany(FormFieldPlacement::class, 'fieldable')->orderBy('sort');
    }

    public function activeFieldPlacements(): MorphMany
    {
        return $this->fieldPlacements()->where('is_active', true)->orderBy('sort');
    }

    /**
     * Back-compat alias for older code / Filament eager loading.
     * This returns the global FormField models attached through placements.
     */
    public function fields(): HasManyThrough
    {
        return $this->hasManyThrough(
            FormField::class,
            FormFieldPlacement::class,
            'fieldable_id',     // FK on placements
            'id',               // FK on form_fields
            'id',               // local key on application_forms
            'form_field_id'     // local key on placements
        )
            ->where('form_field_placements.fieldable_type', self::class)
            ->orderBy('form_field_placements.sort');
    }

    /**
     * @deprecated Prefer fieldPlacements + helpers below.
     */
    public function activeFields()
    {
        return $this->fieldPlacements->map->field;
    }

    /**
     * Ordered field placements (active only) with fields loaded.
     * Returns a Collection<FormFieldPlacement>.
     */
    public function activePlacements(): Collection
    {
        // Prefer already-loaded relation to avoid queries.
        $placements = $this->relationLoaded('fieldPlacements')
            ? $this->fieldPlacements
            : $this->fieldPlacements()->with('field')->get();

        return $placements
            ->filter(fn (FormFieldPlacement $p) => (bool) $p->is_active && $p->field)
            ->sortBy('sort')
            ->values();
    }

    /**
     * Keys in order.
     * Example: ['message', 'city', ...]
     */
    public function fieldKeys(bool $activeOnly = true): array
    {
        $placements = $activeOnly
            ? $this->activePlacements()
            : (
                $this->relationLoaded('fieldPlacements')
                    ? $this->fieldPlacements
                    : $this->fieldPlacements()->with('field')->get()
            );

        return $placements
            ->map(fn (FormFieldPlacement $p) => $p->field?->key)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Required keys (typically active only).
     * Example: ['message', 'city']
     */
    public function requiredKeys(bool $activeOnly = true): array
    {
        $placements = $activeOnly
            ? $this->activePlacements()
            : (
                $this->relationLoaded('fieldPlacements')
                    ? $this->fieldPlacements
                    : $this->fieldPlacements()->with('field')->get()
            );

        return $placements
            ->filter(fn (FormFieldPlacement $p) => (bool) $p->is_required)
            ->map(fn (FormFieldPlacement $p) => $p->field?->key)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Map of key => placement (active only by default).
     *
     * @return array<string, FormFieldPlacement>
     */
    public function placementMap(bool $activeOnly = true): array
    {
        $placements = $activeOnly
            ? $this->activePlacements()
            : (
                $this->relationLoaded('fieldPlacements')
                    ? $this->fieldPlacements
                    : $this->fieldPlacements()->with('field')->get()
            );

        return $placements
            ->filter(fn (FormFieldPlacement $p) => $p->field)
            ->mapWithKeys(fn (FormFieldPlacement $p) => [$p->field->key => $p])
            ->all();
    }

    public function thankYouIsHtml(): bool
    {
        return $this->thank_you_format === self::THANK_YOU_HTML;
    }

    public function thankYouIsWysiwyg(): bool
    {
        return $this->thank_you_format === self::THANK_YOU_WYSIWYG;
    }

    public function thankYouRendersHtml(): bool
    {
        return in_array($this->thank_you_format, [self::THANK_YOU_HTML, self::THANK_YOU_WYSIWYG], true);
    }

    public function thankYouContent(): string
    {
        return (string) ($this->thank_you_content ?? '');
    }

    protected static function booted(): void
    {
        static::created(function (self $form) {
            // Ensure the global field exists
            $message = FormField::query()->firstOrCreate(
                ['key' => 'message'],
                [
                    'type'      => 'textarea',
                    'label'     => 'Why do you want to volunteer?',
                    'help_text' => null,
                    'config'    => [
                        'rows'        => 5,
                        'min'         => 10,
                        'max'         => 5000,
                        'placeholder' => 'Share a bit about your heart to serve...',
                    ],
                ]
            );

            // Attach to this specific form via placement
            FormFieldPlacement::query()->firstOrCreate(
                [
                    'fieldable_type' => self::class,
                    'fieldable_id'   => $form->id,
                    'form_field_id'  => $message->id,
                ],
                [
                    'is_required' => true,
                    'is_active'   => true,
                    'sort'        => 10,
                ]
            );
        });

        static::saving(function (self $form) {
            if (! $form->slug && $form->name) {
                $form->slug = Str::slug($form->name);
            }
        });
    }
}
