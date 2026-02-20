<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;

class HomePageContent extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected array $pendingSeoMeta = [];

    protected $fillable = [
        'language_id',
        'seo_title',
        'seo_description',
        'seo_og_image',
        'hero_intro',
        'meeting_schedule',
        'meeting_location',
        'hero_image_id',
        'featured_image_id',
        'og_image_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'hero_image_id');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'featured_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'og_image_id');
    }

    public function imageables(): MorphMany
    {
        return $this->morphMany(Imageable::class, 'imageable');
    }

    public function imageGroupables(): MorphMany
    {
        return $this->morphMany(ImageGroupable::class, 'image_groupable');
    }

    public function videoables(): MorphMany
    {
        return $this->morphMany(Videoable::class, 'videoable');
    }

    public function seoMeta(): MorphMany
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            $model->syncPendingSeoMeta();
        });
    }

    public function getSeoTitleAttribute(): ?string
    {
        return $this->resolvedSeoMeta()?->seo_title;
    }

    public function setSeoTitleAttribute(?string $value): void
    {
        $this->pendingSeoMeta['seo_title'] = $value;
        unset($this->attributes['seo_title']);
    }

    public function getSeoDescriptionAttribute(): ?string
    {
        return $this->resolvedSeoMeta()?->seo_description;
    }

    public function setSeoDescriptionAttribute(?string $value): void
    {
        $this->pendingSeoMeta['seo_description'] = $value;
        unset($this->attributes['seo_description']);
    }

    public function getSeoOgImageAttribute(): ?string
    {
        return $this->resolvedSeoMeta()?->seo_og_image;
    }

    public function setSeoOgImageAttribute(?string $value): void
    {
        $this->pendingSeoMeta['seo_og_image'] = $value;
        unset($this->attributes['seo_og_image']);
    }

    protected function syncPendingSeoMeta(): void
    {
        if ($this->pendingSeoMeta === []) {
            return;
        }

        $payload = [
            'seo_title' => Arr::get($this->pendingSeoMeta, 'seo_title'),
            'seo_description' => Arr::get($this->pendingSeoMeta, 'seo_description'),
            'seo_og_image' => Arr::get($this->pendingSeoMeta, 'seo_og_image'),
        ];

        $this->pendingSeoMeta = [];

        if (blank($payload['seo_title']) && blank($payload['seo_description']) && blank($payload['seo_og_image'])) {
            $this->seoMeta()
                ->where('target_key', '')
                ->where('language_id', $this->language_id)
                ->delete();
            return;
        }

        $this->seoMeta()->updateOrCreate(
            [
                'target_key' => '',
                'language_id' => $this->language_id,
            ],
            [
                'seo_title' => $payload['seo_title'],
                'seo_description' => $payload['seo_description'],
                'seo_og_image' => $payload['seo_og_image'],
                'is_active' => true,
            ]
        );
    }

    protected function resolvedSeoMeta(): ?SeoMeta
    {
        if (! $this->exists || ! $this->language_id) {
            return null;
        }

        if ($this->relationLoaded('seoMeta')) {
            return $this->seoMeta
                ->where('target_key', '')
                ->where('language_id', $this->language_id)
                ->first();
        }

        return $this->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $this->language_id)
            ->where('is_active', true)
            ->first();
    }
}
