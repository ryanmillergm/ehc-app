<?php

namespace App\Models;

use App\Models\Concerns\SanitizesCmsHtml;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PageTranslation extends Model
{
    use HasFactory;
    use SanitizesCmsHtml;

    /** @var array<string, mixed> */
    protected array $pendingSeoMeta = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'page_id',
        'language_id',
        'title',
        'slug',
        'description',
        'content',
        'template',
        'theme',
        'hero_mode',
        'hero_title',
        'hero_subtitle',
        'hero_cta_text',
        'hero_cta_url',
        'layout_data',
        'seo_title',
        'seo_description',
        'seo_og_image',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'layout_data' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public static function getForm($pageId = null): array
    {
        return [
            Select::make('page_id')
                ->hidden( function () use ($pageId) {
                    return $pageId != null;
                })
                ->label('Page')
                ->relationship('page', 'title')
                ->required(),
            Select::make('language_id')
                ->label('Language')
                ->relationship('language', 'title')
                ->required(),
            Textarea::make('title')
                ->rows(2)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                    if (($get('slug') ?? '') !== Str::slug($old)) {
                        return;
                    }

                    $set('slug', Str::slug($state));
                })
                ->required()
                ->helperText('Plain text or HTML accepted. Sanitized on save.'),
            TextInput::make('slug')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->required()
                ->helperText('Plain text or HTML accepted. Sanitized on save.')
                ->columnSpanFull(),
            Textarea::make('content')
                ->required()
                ->helperText('Plain text or HTML accepted. Sanitized on save.')
                ->columnSpanFull(),
            Select::make('template')
                ->options([
                    'standard' => 'Standard',
                    'campaign' => 'Campaign',
                    'story' => 'Story',
                ])
                ->default('standard')
                ->required(),
            Select::make('theme')
                ->options([
                    'default' => 'Default',
                    'warm' => 'Warm',
                    'slate' => 'Slate',
                ])
                ->default('default')
                ->required(),
            Select::make('hero_mode')
                ->options([
                    'none' => 'None',
                    'image' => 'Image',
                    'video' => 'Video',
                    'slider' => 'Slider',
                ])
                ->default('none')
                ->required(),
            Textarea::make('hero_title')
                ->rows(2)
                ->helperText('Plain text or HTML accepted. Sanitized on save.'),
            Textarea::make('hero_subtitle')
                ->helperText('Plain text or HTML accepted. Sanitized on save.')
                ->columnSpanFull(),
            Textarea::make('hero_cta_text')
                ->rows(2)
                ->helperText('Plain text or HTML accepted. Sanitized on save.'),
            TextInput::make('hero_cta_url')
                ->maxLength(500),
            Toggle::make('is_active')
                ->required(),
        ];
    }

    /**
     * Get the Page that owns the PageTranslation.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the Language that owns the PageTranslation.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
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
        static::saving(function (self $model): void {
            $model->title = $model->sanitizeCmsField($model->title);
            $model->description = $model->sanitizeCmsField($model->description);
            $model->content = $model->sanitizeCmsField($model->content);
            $model->hero_title = $model->sanitizeCmsField($model->hero_title);
            $model->hero_subtitle = $model->sanitizeCmsField($model->hero_subtitle);
            $model->hero_cta_text = $model->sanitizeCmsField($model->hero_cta_text);
            $model->layout_data = $model->sanitizeLayoutData($model->layout_data);
        });

        static::saved(function (self $model): void {
            $model->syncPendingSeoMeta();
        });

        static::created(function (self $model): void {
            $model->ensureCanonicalSeoMetaRow();
        });
    }

    /**
     * @param mixed $layoutData
     * @return array<string, mixed>|null
     */
    protected function sanitizeLayoutData($layoutData): ?array
    {
        if (! is_array($layoutData)) {
            return null;
        }

        $singleKeys = [
            'eyebrow',
            'cta_secondary_text',
            'faq_teaser_title',
            'faq_teaser_body',
        ];

        foreach ($singleKeys as $key) {
            if (isset($layoutData[$key]) && is_string($layoutData[$key])) {
                $layoutData[$key] = $this->sanitizeCmsField($layoutData[$key]);
            }
        }

        foreach (['trust_badges', 'quick_facts'] as $listKey) {
            if (! isset($layoutData[$listKey]) || ! is_array($layoutData[$listKey])) {
                continue;
            }

            $layoutData[$listKey] = array_map(function ($value) {
                return is_string($value) ? $this->sanitizeCmsField($value) : $value;
            }, $layoutData[$listKey]);
        }

        if (isset($layoutData['impact_stats']) && is_array($layoutData['impact_stats'])) {
            $layoutData['impact_stats'] = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                if (isset($item['label']) && is_string($item['label'])) {
                    $item['label'] = $this->sanitizeCmsField($item['label']);
                }

                if (isset($item['value']) && is_string($item['value'])) {
                    $item['value'] = $this->sanitizeCmsField($item['value']);
                }

                return $item;
            }, $layoutData['impact_stats']);
        }

        return $layoutData;
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

    protected function ensureCanonicalSeoMetaRow(): void
    {
        if (! $this->exists || ! $this->language_id) {
            return;
        }

        $this->seoMeta()->updateOrCreate(
            [
                'target_key' => '',
                'language_id' => $this->language_id,
            ],
            [
                'seo_title' => null,
                'seo_description' => null,
                'seo_og_image' => null,
                'is_active' => true,
            ]
        );
    }


    /**
     * Scope Get Translation with Page by Slug
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeTranslationBySlug(Builder $query, $slug)
    {
        $query->with('page')->where('slug', $slug);
    }


    /**
     * Scope Get Active Translation and Page
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeActiveTranslation(Builder $query)
    {
        $query->where('is_active', true);
    }


    /**
     * Scope Get Translation by Language
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeByLanguage(Builder $query, $language)
    {
        $query->where( 'language_id', $language);
    }
}
