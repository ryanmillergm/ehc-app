<?php

namespace App\Models;

use App\Models\Concerns\SanitizesCmsHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeSection extends Model
{
    use HasFactory;
    use SanitizesCmsHtml;

    protected $fillable = [
        'language_id',
        'section_key',
        'eyebrow',
        'heading',
        'subheading',
        'body',
        'note',
        'cta_primary_label',
        'cta_primary_url',
        'cta_secondary_label',
        'cta_secondary_url',
        'cta_tertiary_label',
        'cta_tertiary_url',
        'image_id',
        'meta',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $section): void {
            $section->eyebrow = $section->sanitizeCmsField($section->eyebrow);
            $section->heading = $section->sanitizeCmsField($section->heading);
            $section->subheading = $section->sanitizeCmsField($section->subheading);
            $section->body = $section->sanitizeCmsField($section->body);
            $section->note = $section->sanitizeCmsField($section->note);
            $section->cta_primary_label = $section->sanitizeCmsField($section->cta_primary_label);
            $section->cta_secondary_label = $section->sanitizeCmsField($section->cta_secondary_label);
            $section->cta_tertiary_label = $section->sanitizeCmsField($section->cta_tertiary_label);
        });
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
